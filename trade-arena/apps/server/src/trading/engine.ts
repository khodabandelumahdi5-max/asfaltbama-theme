import { randomUUID } from "node:crypto";
import { eq } from "drizzle-orm";
import { z } from "zod";
import {
  MAINTENANCE_MARGIN_RATE,
  MAX_LEVERAGE,
  MIN_LEVERAGE,
  MIN_MARGIN,
  SYMBOL,
  TAKER_FEE_RATE,
  round,
  type AccountView,
  type ClosedTradeView,
  type CloseReason,
  type PositionView,
  type Side,
} from "@arena/shared";
import { db } from "../db/client";
import { trades } from "../db/schema";
import { AppError } from "../lib/errors";
import { logger } from "../logger";
import { market } from "../market/state";
import { redis } from "../redis/client";
import { keys } from "../redis/keys";
import { getRank } from "../leaderboard/leaderboard";
import { emitToUser, publishFeed } from "../realtime/bus";
import { getActiveTournamentId } from "../tournament/store";

export const openRequestSchema = z.object({
  side: z.enum(["LONG", "SHORT"]),
  leverage: z.number().int().min(MIN_LEVERAGE).max(MAX_LEVERAGE),
  margin: z.number().finite().min(MIN_MARGIN),
});

const OPEN_ERRORS: Record<string, [string, number]> = {
  TOURNAMENT_NOT_ACTIVE: ["Tournament is not live right now", 409],
  NOT_JOINED: ["Join the tournament first", 409],
  POSITION_EXISTS: ["Close your current position first", 409],
  INSUFFICIENT_BALANCE: ["Insufficient balance for margin + fee", 400],
};

export async function getAccount(tid: string, uid: string): Promise<AccountView | null> {
  const [acct, rank] = await Promise.all([redis.hgetall(keys.tAccount(tid, uid)), getRank(tid, uid)]);
  if (!acct.bal) return null;
  const balance = Number(acct.bal);
  const start = Number(acct.start);
  return {
    tournamentId: tid,
    balance: round(balance, 4),
    startingBalance: start,
    realizedPnlPct: round(((balance - start) / start) * 100, 4),
    trades: Number(acct.trades ?? 0),
    wins: Number(acct.wins ?? 0),
    rank,
  };
}

export async function getPosition(tid: string, uid: string): Promise<PositionView | null> {
  const p = await redis.hgetall(keys.tPosition(tid, uid));
  if (!p.tradeId) return null;
  return {
    tradeId: p.tradeId,
    side: p.side as Side,
    leverage: Number(p.lev),
    margin: Number(p.margin),
    qty: Number(p.qty),
    entryPrice: Number(p.entry),
    liquidationPrice: Number(p.liq),
    openFee: Number(p.fee),
    openedAt: Number(p.openedAt),
  };
}

function requireFreshPrice(): number {
  if (!market.isFresh()) throw new AppError("MARKET_STALE", "Price feed unavailable, try again in a moment", 503);
  return market.price;
}

export async function openPosition(
  uid: string,
  displayName: string,
  input: unknown,
): Promise<{ position: PositionView; account: AccountView }> {
  const parsed = openRequestSchema.safeParse(input);
  if (!parsed.success) throw new AppError("INVALID_ORDER", parsed.error.issues[0]?.message ?? "Invalid order");
  const { side, leverage, margin } = parsed.data;
  const tid = await getActiveTournamentId();
  if (!tid) throw new AppError("TOURNAMENT_NOT_ACTIVE", "No tournament running", 409);

  const price = requireFreshPrice();
  const tradeId = randomUUID();
  const now = Date.now();
  const res = await redis.openPosition(
    keys.tAccount(tid, uid),
    keys.tPosition(tid, uid),
    keys.tLiqLong(tid),
    keys.tLiqShort(tid),
    keys.tMeta(tid),
    uid,
    tradeId,
    side,
    String(leverage),
    String(round(margin, 2)),
    String(price),
    String(TAKER_FEE_RATE),
    String(MAINTENANCE_MARGIN_RATE),
    String(now),
  );
  if (!res || res[0] !== "OK") {
    const code = res?.[1] ?? "INTERNAL";
    const [msg, status] = OPEN_ERRORS[code] ?? ["Order rejected", 400];
    throw new AppError(code, msg, status);
  }

  const position: PositionView = {
    tradeId,
    side,
    leverage,
    margin: round(margin, 2),
    qty: Number(res[1]),
    entryPrice: price,
    liquidationPrice: Number(res[2]),
    openFee: Number(res[3]),
    openedAt: now,
  };

  try {
    db.insert(trades)
      .values({
        id: tradeId,
        tournamentId: tid,
        userId: uid,
        symbol: SYMBOL,
        side,
        leverage,
        margin: position.margin,
        qty: position.qty,
        entryPrice: price,
        liquidationPrice: position.liquidationPrice,
        openFee: position.openFee,
        status: "OPEN",
        openedAt: now,
      })
      .run();
  } catch (err) {
    // Redis is the source of truth for live state; a failed audit insert must not orphan the position.
    logger.error({ err, tradeId }, "failed to persist opened trade");
  }

  const account = (await getAccount(tid, uid))!;
  emitToUser(uid, "position:update", position);
  emitToUser(uid, "account:update", account);
  publishFeed({ kind: "OPEN", name: displayName, side, leverage });
  return { position, account };
}

export async function closePosition(
  tid: string,
  uid: string,
  reason: CloseReason,
  exitPrice = market.price,
): Promise<{ trade: ClosedTradeView; account: AccountView } | null> {
  const res = await redis.closePosition(
    keys.tAccount(tid, uid),
    keys.tPosition(tid, uid),
    keys.tLiqLong(tid),
    keys.tLiqShort(tid),
    keys.tLeaderboard(tid),
    keys.tLeaderboardDirty(tid),
    uid,
    String(exitPrice),
    String(TAKER_FEE_RATE),
    reason,
  );
  if (!res) return null;

  // Layout: tradeId, side, lev, margin, qty, entry, liq, openFee, openedAt, exit, closeFee, realized, bal, pnlPct
  const [tradeId, side, lev, margin, , entry, , , , exit, closeFee, realized, bal] = res as string[] as [
    string, string, string, string, string, string, string, string, string, string, string, string, string,
  ];
  const closedAt = Date.now();
  const realizedPnl = Number(realized);
  const marginNum = Number(margin);

  try {
    db.update(trades)
      .set({
        exitPrice: Number(exit),
        closeFee: Number(closeFee),
        realizedPnl,
        closeReason: reason,
        status: "CLOSED",
        closedAt,
      })
      .where(eq(trades.id, tradeId))
      .run();
  } catch (err) {
    logger.error({ err, tradeId }, "failed to persist closed trade");
  }

  const trade: ClosedTradeView = {
    tradeId,
    side: side as Side,
    leverage: Number(lev),
    margin: marginNum,
    entryPrice: Number(entry),
    exitPrice: Number(exit),
    realizedPnl: round(realizedPnl, 4),
    roePct: round((realizedPnl / marginNum) * 100, 2),
    reason,
    closedAt,
    balance: round(Number(bal), 4),
  };
  const account = (await getAccount(tid, uid))!;
  emitToUser(uid, "trade:closed", trade);
  emitToUser(uid, "position:update", null);
  emitToUser(uid, "account:update", account);

  const name = (await redis.hget(keys.userNames, uid)) ?? "Trader";
  publishFeed({
    kind: reason === "LIQUIDATION" ? "LIQUIDATION" : "CLOSE",
    name,
    side: trade.side,
    leverage: trade.leverage,
    pnl: trade.realizedPnl,
    roePct: trade.roePct,
  });
  return { trade, account };
}

export async function closeByUser(uid: string): Promise<{ trade: ClosedTradeView; account: AccountView }> {
  const tid = await getActiveTournamentId();
  if (!tid) throw new AppError("TOURNAMENT_NOT_ACTIVE", "No tournament running", 409);
  const price = requireFreshPrice();
  const result = await closePosition(tid, uid, "MANUAL", price);
  if (!result) throw new AppError("NO_POSITION", "No open position", 404);
  return result;
}

/**
 * Leader-only: liquidates every position whose liquidation price was crossed.
 * Positions are indexed in two ZSETs scored by liquidation price, so each sweep is
 * O(log N + K) regardless of how many positions are open.
 */
export async function sweepLiquidations(tid: string, price: number): Promise<number> {
  const [longs, shorts] = await Promise.all([
    redis.zrangebyscore(keys.tLiqLong(tid), price, "+inf"),
    redis.zrangebyscore(keys.tLiqShort(tid), "-inf", price),
  ]);
  const victims = [...longs, ...shorts];
  if (victims.length === 0) return 0;
  const results = await Promise.allSettled(victims.map((uid) => closePosition(tid, uid, "LIQUIDATION")));
  for (const r of results) if (r.status === "rejected") logger.error({ err: r.reason }, "liquidation failed");
  return victims.length;
}

/** All users with an open position in the tournament (each position lives in exactly one liq index). */
export async function openPositionHolders(tid: string): Promise<string[]> {
  const [longs, shorts] = await Promise.all([
    redis.zrange(keys.tLiqLong(tid), 0, -1),
    redis.zrange(keys.tLiqShort(tid), 0, -1),
  ]);
  return [...longs, ...shorts];
}
