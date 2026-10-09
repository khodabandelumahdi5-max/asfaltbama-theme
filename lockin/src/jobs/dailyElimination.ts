import "server-only";
import type { PoolClient } from "pg";
import { db } from "@/lib/db";

export interface Elimination {
  poolId: string;
  wallet: string;
  eliminatedOnDay: number;
  reason: "MISSED_DEADLINE";
}

export interface DailyEliminationResult {
  asOf: string;
  eliminated: Elimination[];
  completedPoolIds: string[];
}

/**
 * Eliminates every live participant who has no proof for a day whose deadline
 * has passed as of `asOf`. Day N closes at (start_date + N) 00:00 UTC +
 * lockin_grace(), so the last closed day is
 * ((asOf - grace) as a UTC date) - start_date, capped at 21.
 *
 * Any submission counts, including one still PENDING review: being judged
 * slowly is not the participant's fault. REJECTED proofs already eliminated
 * the participant through peer review.
 *
 * The streak is frozen, not reset: current_streak keeps the verified count
 * they reached. Idempotent: rows that are already eliminated are skipped.
 */
export async function eliminateMissedDays(
  client: PoolClient,
  asOf: Date,
  poolId: string | null = null,
): Promise<Elimination[]> {
  const res = await client.query<{ pool_id: string; wallet_address: string; eliminated_on_day: number }>(
    `WITH missed AS (
       SELECT pp.id, MIN(d.day) AS first_missed_day
         FROM pool_participants pp
         JOIN challenge_pools p ON p.id = pp.pool_id
        CROSS JOIN LATERAL generate_series(
                 1,
                 LEAST(21, ((($1::timestamptz - lockin_grace()) AT TIME ZONE 'UTC')::date - p.start_date))
               ) AS d(day)
        WHERE p.status IN ('ACTIVE', 'COMPLETED')
          AND ($2::uuid IS NULL OR p.id = $2::uuid)
          AND NOT pp.is_eliminated
          AND NOT EXISTS (
                SELECT 1 FROM proof_submissions s
                 WHERE s.pool_id = pp.pool_id
                   AND s.wallet_address = pp.wallet_address
                   AND s.day_number = d.day)
        GROUP BY pp.id
     )
     UPDATE pool_participants pp
        SET is_eliminated = TRUE,
            eliminated_on_day = missed.first_missed_day,
            elimination_reason = 'MISSED_DEADLINE'
       FROM missed
      WHERE pp.id = missed.id AND NOT pp.is_eliminated
     RETURNING pp.pool_id, pp.wallet_address, pp.eliminated_on_day`,
    [asOf.toISOString(), poolId],
  );

  return res.rows.map((r) => ({
    poolId: r.pool_id,
    wallet: r.wallet_address,
    eliminatedOnDay: r.eliminated_on_day,
    reason: "MISSED_DEADLINE" as const,
  }));
}

/**
 * Daily job: eliminate missed days, then mark pools whose final deadline
 * (day 21's grace period) has passed as COMPLETED so settlement can pick them up.
 */
export async function runDailyElimination(asOf: Date = new Date()): Promise<DailyEliminationResult> {
  const client = await db.connect();
  try {
    await client.query("BEGIN");
    const eliminated = await eliminateMissedDays(client, asOf);
    const completed = await client.query<{ id: string }>(
      `UPDATE challenge_pools
          SET status = 'COMPLETED'
        WHERE status = 'ACTIVE'
          AND ((end_date + 1)::timestamp AT TIME ZONE 'UTC') + lockin_grace() <= $1::timestamptz
        RETURNING id`,
      [asOf.toISOString()],
    );
    await client.query("COMMIT");

    for (const e of eliminated) {
      console.info(JSON.stringify({ event: "participant_eliminated", ...e, asOf: asOf.toISOString() }));
    }

    return {
      asOf: asOf.toISOString(),
      eliminated,
      completedPoolIds: completed.rows.map((r) => r.id),
    };
  } catch (err) {
    await client.query("ROLLBACK").catch(() => {});
    throw err;
  } finally {
    client.release();
  }
}
