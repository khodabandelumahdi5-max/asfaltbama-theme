import { createHmac, randomUUID, timingSafeEqual } from "node:crypto";
import { and, desc, eq, lt, ne } from "drizzle-orm";
import { z } from "zod";
import type { DepositNetwork, DepositView } from "@arena/shared";
import { config } from "../config";
import { db } from "../db/client";
import { deposits, users, type Deposit } from "../db/schema";
import { AppError } from "../lib/errors";
import { amountTag, depositReference } from "../lib/ids";
import { logger } from "../logger";
import { emitToUser } from "../realtime/bus";
import { notifyTelegram } from "../realtime/notify";
import { applyTickets } from "./ledger";

const MICRO = 1_000_000;

export const createDepositSchema = z.object({
  network: z.enum(["TRC20", "TON"]),
  tickets: z.number().int().min(1).max(1_000),
});

export function formatMicro(micro: number): string {
  const whole = Math.floor(micro / MICRO);
  const frac = String(micro % MICRO).padStart(6, "0");
  return `${whole}.${frac}`;
}

/** Parses a decimal USDT string into micro-units without floating point error. */
export function parseMicro(amount: string): number {
  if (!/^\d+(\.\d{1,6})?$/.test(amount)) throw new AppError("INVALID_AMOUNT", "Amount must be a decimal string with ≤6 decimals");
  const [whole, frac = ""] = amount.split(".");
  return Number(whole) * MICRO + Number(frac.padEnd(6, "0"));
}

function addressFor(network: DepositNetwork): string {
  return network === "TRC20" ? config.DEPOSIT_ADDRESS_TRC20 : config.DEPOSIT_ADDRESS_TON;
}

export function toDepositView(d: Deposit): DepositView {
  return {
    id: d.id,
    reference: d.reference,
    network: d.network,
    asset: "USDT",
    address: d.address,
    amount: formatMicro(d.amountMicro),
    memo: d.network === "TON" ? d.reference : null,
    tickets: d.tickets,
    status: d.status,
    expiresAt: d.expiresAt,
    createdAt: d.createdAt,
  };
}

/**
 * Creates a deposit intent with a unique reference code.
 *  - TON: the reference is the transfer comment (memo) and is the primary match key.
 *  - TRC20: transfers carry no memo, so the expected amount gets a unique micro-tag
 *    (e.g. 5.004217 USDT). A partial unique index guarantees the tagged amount is unique
 *    among PENDING intents per network, so an amount alone identifies the deposit.
 *    TON intents are tagged too, which lets memo-less TON transfers still be matched.
 */
export function createDeposit(userId: string, input: unknown): DepositView {
  const parsed = createDepositSchema.safeParse(input);
  if (!parsed.success) throw new AppError("INVALID_DEPOSIT", parsed.error.issues[0]?.message ?? "Invalid deposit request");
  const { network, tickets } = parsed.data;
  const base = Math.round(tickets * config.TICKET_PRICE_USDT * MICRO);
  const now = Date.now();

  for (let attempt = 0; attempt < 10; attempt++) {
    const row = db
      .insert(deposits)
      .values({
        id: randomUUID(),
        reference: depositReference(),
        userId,
        network,
        address: addressFor(network),
        amountMicro: base + amountTag(),
        tickets,
        status: "PENDING",
        expiresAt: now + config.DEPOSIT_TTL_SEC * 1000,
      })
      .onConflictDoNothing()
      .returning()
      .get();
    if (row) return toDepositView(row);
  }
  throw new AppError("DEPOSIT_BUSY", "Could not allocate a deposit slot, please retry", 503);
}

export function listDeposits(userId: string, limit = 20): DepositView[] {
  return db
    .select()
    .from(deposits)
    .where(eq(deposits.userId, userId))
    .orderBy(desc(deposits.createdAt))
    .limit(limit)
    .all()
    .map(toDepositView);
}

export const webhookSchema = z.object({
  network: z.enum(["TRC20", "TON"]),
  txHash: z.string().min(8).max(128),
  amount: z.string(),
  asset: z.literal("USDT").default("USDT"),
  to: z.string().optional(),
  reference: z.string().optional(),
  memo: z.string().optional(),
  confirmations: z.number().int().nonnegative().optional(),
});
export type DepositWebhook = z.infer<typeof webhookSchema>;

const MAX_SKEW_SEC = 300;

/** Verifies `x-signature: hex(HMAC_SHA256(secret, "<x-timestamp>.<rawBody>"))` with replay protection. */
export function verifyWebhookSignature(rawBody: string, timestamp: string | undefined, signature: string | undefined): void {
  if (!timestamp || !signature) throw new AppError("WEBHOOK_UNSIGNED", "Missing signature headers", 401);
  const ts = Number(timestamp);
  if (!Number.isFinite(ts) || Math.abs(Date.now() / 1000 - ts) > MAX_SKEW_SEC) {
    throw new AppError("WEBHOOK_STALE", "Timestamp outside allowed window", 401);
  }
  const expected = createHmac("sha256", config.DEPOSIT_WEBHOOK_SECRET).update(`${timestamp}.${rawBody}`).digest();
  const given = Buffer.from(signature, "hex");
  if (given.length !== expected.length || !timingSafeEqual(given, expected)) {
    throw new AppError("WEBHOOK_BAD_SIGNATURE", "Invalid signature", 401);
  }
}

export function signWebhook(rawBody: string, timestamp = Math.floor(Date.now() / 1000)): { timestamp: string; signature: string } {
  const signature = createHmac("sha256", config.DEPOSIT_WEBHOOK_SECRET).update(`${timestamp}.${rawBody}`).digest("hex");
  return { timestamp: String(timestamp), signature };
}

export type WebhookOutcome =
  | { status: "credited"; deposit: DepositView; tickets: number }
  | { status: "duplicate"; deposit: DepositView }
  | { status: "underpaid"; deposit: DepositView };

/**
 * Idempotent deposit confirmation. Matching order: explicit reference → memo (TON comment) → unique tagged amount.
 * Credits tickets + first-deposit referral bonus inside one SQLite transaction.
 */
export async function handleDepositWebhook(payload: DepositWebhook): Promise<WebhookOutcome> {
  const receivedMicro = parseMicro(payload.amount);
  if (payload.to && payload.to !== addressFor(payload.network)) {
    throw new AppError("WEBHOOK_WRONG_ADDRESS", "Transfer not addressed to our deposit wallet", 422);
  }

  const byTx = db.select().from(deposits).where(eq(deposits.txHash, payload.txHash)).get();
  if (byTx) return { status: "duplicate", deposit: toDepositView(byTx) };

  const ref = (payload.reference ?? payload.memo)?.trim().toUpperCase();
  let deposit: Deposit | undefined;
  if (ref) deposit = db.select().from(deposits).where(eq(deposits.reference, ref)).get();
  if (!deposit) {
    deposit = db
      .select()
      .from(deposits)
      .where(and(eq(deposits.network, payload.network), eq(deposits.amountMicro, receivedMicro), eq(deposits.status, "PENDING")))
      .get();
  }
  if (!deposit) throw new AppError("DEPOSIT_NOT_FOUND", "No matching deposit intent", 404);
  if (deposit.network !== payload.network) throw new AppError("WEBHOOK_NETWORK_MISMATCH", "Network mismatch", 422);
  if (deposit.status === "CONFIRMED") return { status: "duplicate", deposit: toDepositView(deposit) };

  if (receivedMicro < deposit.amountMicro) {
    const row = db
      .update(deposits)
      .set({ status: "UNDERPAID", receivedMicro, txHash: payload.txHash })
      .where(eq(deposits.id, deposit.id))
      .returning()
      .get()!;
    logger.warn({ depositId: deposit.id, receivedMicro, expected: deposit.amountMicro }, "underpaid deposit");
    emitToUser(deposit.userId, "wallet:update", { tickets: currentTickets(deposit.userId), deposit: toDepositView(row) });
    return { status: "underpaid", deposit: toDepositView(row) };
  }

  const target = deposit;
  const { row, tickets, referrer } = db.transaction((tx) => {
    const updated = tx
      .update(deposits)
      .set({ status: "CONFIRMED", receivedMicro, txHash: payload.txHash, confirmedAt: Date.now() })
      .where(and(eq(deposits.id, target.id), ne(deposits.status, "CONFIRMED")))
      .returning()
      .get();
    if (!updated) throw new AppError("DEPOSIT_RACE", "Deposit already processed", 409);
    const balance = applyTickets(tx, target.userId, target.tickets, "DEPOSIT", target.id);

    let referrerId: string | null = null;
    const user = tx.select().from(users).where(eq(users.id, target.userId)).get();
    if (user?.referredBy && config.REFERRAL_BONUS_TICKETS > 0) {
      const priorConfirmed = tx
        .select({ id: deposits.id })
        .from(deposits)
        .where(and(eq(deposits.userId, target.userId), eq(deposits.status, "CONFIRMED"), ne(deposits.id, target.id)))
        .get();
      if (!priorConfirmed) {
        applyTickets(tx, user.referredBy, config.REFERRAL_BONUS_TICKETS, "REFERRAL_BONUS", target.userId);
        referrerId = user.referredBy;
      }
    }
    return { row: updated, tickets: balance, referrer: referrerId };
  });

  const view = toDepositView(row);
  emitToUser(target.userId, "wallet:update", { tickets, deposit: view });
  await notifyTelegram(target.userId, `✅ Deposit ${view.amount} USDT confirmed — ${target.tickets} 🎟 tickets added (balance: ${tickets}).`);
  if (referrer) {
    emitToUser(referrer, "wallet:update", { tickets: currentTickets(referrer) });
    await notifyTelegram(referrer, `🎁 Your friend made their first deposit — +${config.REFERRAL_BONUS_TICKETS} 🎟 referral bonus!`);
  }
  logger.info({ depositId: target.id, userId: target.userId, tickets: target.tickets }, "deposit credited");
  return { status: "credited", deposit: view, tickets };
}

function currentTickets(userId: string): number {
  return db.select({ tickets: users.tickets }).from(users).where(eq(users.id, userId)).get()?.tickets ?? 0;
}

/** Leader-only housekeeping: frees tagged amounts of stale intents. */
export function expireDeposits(): number {
  const res = db
    .update(deposits)
    .set({ status: "EXPIRED" })
    .where(and(eq(deposits.status, "PENDING"), lt(deposits.expiresAt, Date.now())))
    .run();
  return res.changes;
}
