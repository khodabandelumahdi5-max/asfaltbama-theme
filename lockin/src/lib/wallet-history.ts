import "server-only";
import { db } from "./db";
import type { PoolResult, RefundView } from "./types";

const RESULT_LIMIT = 5;
const REFUND_LIMIT = 10;

/** Finished pools (COMPLETED or SETTLED) this wallet played in, newest first, with its outcome and payout. */
export async function loadPoolResults(wallet: string): Promise<PoolResult[]> {
  const res = await db.query(
    `SELECT p.id, p.title, p.start_date::text, p.end_date::text, p.status,
            pp.is_eliminated, pp.eliminated_on_day, pp.elimination_reason, pp.current_streak,
            pp.stake_lamports::text,
            s.survivor_count, s.total_lamports::text, s.platform_fee_lamports::text,
            s.payout_per_survivor_lamports::text, s.paid_out_at,
            po.amount_lamports::text AS payout_amount, po.status AS payout_status, po.tx_sig AS payout_tx_sig
       FROM pool_participants pp
       JOIN challenge_pools p ON p.id = pp.pool_id
       LEFT JOIN pool_settlements s ON s.pool_id = p.id
       LEFT JOIN payouts po
              ON po.pool_id = p.id AND po.recipient_wallet = pp.wallet_address AND po.kind = 'SURVIVOR'
      WHERE pp.wallet_address = $1 AND p.status IN ('COMPLETED', 'SETTLED')
      ORDER BY p.end_date DESC
      LIMIT ${RESULT_LIMIT}`,
    [wallet],
  );

  return res.rows.map((r) => ({
    poolId: r.id,
    title: r.title,
    startDate: r.start_date,
    endDate: r.end_date,
    status: r.status,
    survived: !r.is_eliminated,
    eliminatedOnDay: r.eliminated_on_day,
    eliminationReason: r.elimination_reason,
    currentStreak: r.current_streak,
    stakeLamports: r.stake_lamports,
    settlement:
      r.total_lamports === null
        ? null
        : {
            survivorCount: r.survivor_count,
            totalLamports: r.total_lamports,
            platformFeeLamports: r.platform_fee_lamports,
            payoutPerSurvivorLamports: r.payout_per_survivor_lamports,
            paidOutAt: r.paid_out_at ? r.paid_out_at.toISOString() : null,
          },
    payout:
      r.payout_amount === null
        ? null
        : {
            amountLamports: r.payout_amount,
            status: r.payout_status,
            // tx_sig is only shown once the transfer is final.
            txSig: r.payout_status === "CONFIRMED" ? r.payout_tx_sig : null,
          },
  }));
}

const REFUND_STATUS: Record<string, RefundView["status"]> = {
  PENDING: "QUEUED",
  SENT: "SENDING",
  CONFIRMED: "REFUNDED",
  HELD: "UNDER_REVIEW",
};

/** Deposits from this wallet that could not be enrolled, and where their refund stands. */
export async function loadRefunds(wallet: string): Promise<RefundView[]> {
  const res = await db.query(
    `SELECT d.tx_sig, d.amount_lamports::text, d.status AS reason, d.received_at,
            r.status AS refund_status, r.tx_sig AS refund_tx_sig
       FROM deposits d LEFT JOIN refunds r ON r.deposit_tx_sig = d.tx_sig
      WHERE d.from_wallet = $1 AND d.status IN ('WRONG_AMOUNT', 'UNMATCHED', 'DUPLICATE_ENTRY')
      ORDER BY d.received_at DESC
      LIMIT ${REFUND_LIMIT}`,
    [wallet],
  );
  return res.rows.map((r) => {
    const status = r.refund_status ? REFUND_STATUS[r.refund_status] : "QUEUED";
    return {
      depositTxSig: r.tx_sig,
      amountLamports: r.amount_lamports,
      reason: r.reason,
      receivedAt: r.received_at.toISOString(),
      status,
      refundTxSig: status === "REFUNDED" ? r.refund_tx_sig : null,
    };
  });
}
