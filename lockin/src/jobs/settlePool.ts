import "server-only";
import { PublicKey, SystemProgram } from "@solana/web3.js";
import type { PoolClient } from "pg";
import { TREASURY_ADDRESS } from "@/lib/constants";
import { db } from "@/lib/db";
import { eliminateMissedDays, type Elimination } from "./dailyElimination";
import { chunk, computeSettlement } from "./payoutMath";

/**
 * Transfers per Solana transaction. A legacy tx is capped at 1232 bytes; with
 * two signers (escrow + fee payer) and one new account key per transfer, 18
 * leaves comfortable headroom.
 */
export const TRANSFERS_PER_BATCH = 18;

export interface PoolSettlementSummary {
  poolId: string;
  totalLamports: string;
  platformFeeLamports: string;
  dustLamports: string;
  survivorCount: number;
  payoutPerSurvivorLamports: string;
  lateEliminations: Elimination[];
  batches: PayoutBatch[];
}

export interface PayoutBatch {
  batchIndex: number;
  /** Must sign: pays network fees. */
  feePayer: string;
  /** Must sign: source of every transfer. */
  escrow: string;
  transfers: {
    payoutId: string;
    kind: "SURVIVOR" | "PLATFORM_FEE";
    recipient: string;
    lamports: string;
    instruction: {
      programId: string;
      keys: { pubkey: string; isSigner: boolean; isWritable: boolean }[];
      dataBase64: string;
    };
  }[];
}

export interface SettleRunResult {
  asOf: string;
  settled: PoolSettlementSummary[];
  failed: { poolId: string; error: string }[];
}

function settlementWallets() {
  const escrow = TREASURY_ADDRESS;
  const platform = process.env.PLATFORM_TREASURY_ADDRESS ?? "";
  if (!escrow || !platform) {
    throw new Error("NEXT_PUBLIC_TREASURY_ADDRESS and PLATFORM_TREASURY_ADDRESS must both be set");
  }
  // Throws on malformed base58.
  new PublicKey(escrow);
  new PublicKey(platform);
  if (escrow === platform) throw new Error("PLATFORM_TREASURY_ADDRESS must differ from the escrow treasury");
  return { escrow, platform };
}

/** Pools whose day-21 deadline has passed and that are not settled yet. */
async function findDuePools(asOf: Date): Promise<string[]> {
  const res = await db.query<{ id: string }>(
    `SELECT id FROM challenge_pools
      WHERE status IN ('ACTIVE', 'COMPLETED')
        AND ((end_date + 1)::timestamp AT TIME ZONE 'UTC') + lockin_grace() <= $1::timestamptz
      ORDER BY end_date`,
    [asOf.toISOString()],
  );
  return res.rows.map((r) => r.id);
}

async function settleOne(
  client: PoolClient,
  poolId: string,
  asOf: Date,
  wallets: { escrow: string; platform: string },
): Promise<PoolSettlementSummary | null> {
  // Lock the pool; a concurrent run either waits and then sees SETTLED, or skips.
  const pool = await client.query<{ total_locked_lamports: string }>(
    `SELECT total_locked_lamports FROM challenge_pools
      WHERE id = $1 AND status IN ('ACTIVE', 'COMPLETED')
      FOR UPDATE`,
    [poolId],
  );
  if (pool.rowCount === 0) return null;

  // Final elimination pass so nobody who missed day 21 gets paid, even if the
  // daily job has not run since the deadline.
  const lateEliminations = await eliminateMissedDays(client, asOf, poolId);

  const ledger = await client.query<{ staked: string | null }>(
    `SELECT SUM(stake_lamports)::text AS staked FROM pool_participants WHERE pool_id = $1`,
    [poolId],
  );
  const total = BigInt(pool.rows[0].total_locked_lamports);
  if (BigInt(ledger.rows[0].staked ?? "0") !== total) {
    throw new Error(`ledger mismatch: total_locked_lamports=${total} but stakes sum to ${ledger.rows[0].staked}`);
  }

  const survivors = await client.query<{ wallet_address: string }>(
    `SELECT wallet_address FROM pool_participants
      WHERE pool_id = $1 AND NOT is_eliminated
      ORDER BY joined_at, wallet_address`,
    [poolId],
  );
  const split = computeSettlement(total, survivors.rowCount ?? 0);

  await client.query(
    `INSERT INTO pool_settlements
       (pool_id, total_lamports, platform_fee_lamports, dust_lamports, survivor_count,
        payout_per_survivor_lamports, escrow_wallet, platform_wallet)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
    [
      poolId,
      split.total.toString(),
      split.platformFee.toString(),
      split.dust.toString(),
      split.survivorCount,
      split.perSurvivor.toString(),
      wallets.escrow,
      wallets.platform,
    ],
  );

  // Platform transfer first, then survivors in join order.
  const rows: { recipient: string; kind: "SURVIVOR" | "PLATFORM_FEE"; amount: bigint }[] = [];
  const platformTotal = split.platformFee + split.dust;
  if (platformTotal > 0n) rows.push({ recipient: wallets.platform, kind: "PLATFORM_FEE", amount: platformTotal });
  if (split.perSurvivor > 0n) {
    for (const s of survivors.rows) rows.push({ recipient: s.wallet_address, kind: "SURVIVOR", amount: split.perSurvivor });
  }

  const batches = chunk(rows, TRANSFERS_PER_BATCH);
  for (const [batchIndex, batch] of batches.entries()) {
    for (const r of batch) {
      await client.query(
        `INSERT INTO payouts (pool_id, recipient_wallet, kind, amount_lamports, batch_index)
         VALUES ($1, $2, $3, $4, $5)`,
        [poolId, r.recipient, r.kind, r.amount.toString(), batchIndex],
      );
    }
  }

  await client.query(`UPDATE challenge_pools SET status = 'SETTLED' WHERE id = $1`, [poolId]);

  return {
    poolId,
    totalLamports: split.total.toString(),
    platformFeeLamports: split.platformFee.toString(),
    dustLamports: split.dust.toString(),
    survivorCount: split.survivorCount,
    payoutPerSurvivorLamports: split.perSurvivor.toString(),
    lateEliminations,
    batches: await buildPayoutBatches(client, poolId),
  };
}

/**
 * Builds unsigned SystemProgram.transfer instructions for a pool's PENDING
 * payouts, grouped by batch. Each batch is one transaction: the executor adds
 * a recent blockhash, signs with the escrow and platform keys, sends it, then
 * records tx_sig and status on the payout rows.
 */
export async function buildPayoutBatches(client: PoolClient, poolId: string): Promise<PayoutBatch[]> {
  const res = await client.query<{
    id: string;
    recipient_wallet: string;
    kind: "SURVIVOR" | "PLATFORM_FEE";
    amount_lamports: string;
    batch_index: number;
    escrow_wallet: string;
    platform_wallet: string;
  }>(
    `SELECT p.id, p.recipient_wallet, p.kind, p.amount_lamports::text, p.batch_index,
            s.escrow_wallet, s.platform_wallet
       FROM payouts p JOIN pool_settlements s ON s.pool_id = p.pool_id
      WHERE p.pool_id = $1 AND p.status = 'PENDING'
      ORDER BY p.batch_index, (p.kind = 'PLATFORM_FEE') DESC, p.recipient_wallet`,
    [poolId],
  );

  const byBatch = new Map<number, PayoutBatch>();
  for (const r of res.rows) {
    let batch = byBatch.get(r.batch_index);
    if (!batch) {
      batch = { batchIndex: r.batch_index, feePayer: r.platform_wallet, escrow: r.escrow_wallet, transfers: [] };
      byBatch.set(r.batch_index, batch);
    }
    const ix = SystemProgram.transfer({
      fromPubkey: new PublicKey(r.escrow_wallet),
      toPubkey: new PublicKey(r.recipient_wallet),
      lamports: BigInt(r.amount_lamports),
    });
    batch.transfers.push({
      payoutId: r.id,
      kind: r.kind,
      recipient: r.recipient_wallet,
      lamports: r.amount_lamports,
      instruction: {
        programId: ix.programId.toBase58(),
        keys: ix.keys.map((k) => ({ pubkey: k.pubkey.toBase58(), isSigner: k.isSigner, isWritable: k.isWritable })),
        dataBase64: Buffer.from(ix.data).toString("base64"),
      },
    });
  }
  return [...byBatch.values()];
}

/** Settles every due pool, each in its own transaction so one bad pool cannot block the rest. */
export async function settleDuePools(asOf: Date = new Date()): Promise<SettleRunResult> {
  const wallets = settlementWallets();
  const result: SettleRunResult = { asOf: asOf.toISOString(), settled: [], failed: [] };

  for (const poolId of await findDuePools(asOf)) {
    const client = await db.connect();
    try {
      await client.query("BEGIN");
      const summary = await settleOne(client, poolId, asOf, wallets);
      await client.query("COMMIT");
      if (summary) {
        result.settled.push(summary);
        console.info(
          JSON.stringify({
            event: "pool_settled",
            poolId,
            survivors: summary.survivorCount,
            perSurvivor: summary.payoutPerSurvivorLamports,
            platformFee: summary.platformFeeLamports,
            dust: summary.dustLamports,
          }),
        );
      }
    } catch (err) {
      await client.query("ROLLBACK").catch(() => {});
      const message = err instanceof Error ? err.message : String(err);
      console.error(JSON.stringify({ event: "pool_settlement_failed", poolId, error: message }));
      result.failed.push({ poolId, error: message });
    } finally {
      client.release();
    }
  }
  return result;
}
