import { NextResponse, type NextRequest } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { parseSignedRequest } from "@/lib/signed-request";

export const runtime = "nodejs";

/** Net votes needed to settle a submission either way. */
const CONSENSUS_THRESHOLD = 3;

const payload = z.object({
  submissionId: z.uuid(),
  vote: z.union([z.literal(1), z.literal(-1)]),
});

export async function POST(req: NextRequest) {
  const parsed = parseSignedRequest(await req.json().catch(() => null), "review", payload);
  if (!parsed.ok) return NextResponse.json({ error: parsed.error }, { status: 400 });

  const { submissionId, vote } = parsed.data;
  const client = await db.connect();
  try {
    await client.query("BEGIN");

    // Lock the submission so concurrent votes settle it exactly once.
    const sub = await client.query(
      `SELECT s.id, s.pool_id, s.wallet_address, s.day_number, s.status
         FROM proof_submissions s
         JOIN pool_participants me
           ON me.pool_id = s.pool_id AND me.wallet_address = $2 AND NOT me.is_eliminated
        WHERE s.id = $1
        FOR UPDATE OF s`,
      [submissionId, parsed.wallet],
    );
    if (sub.rowCount === 0) {
      await client.query("ROLLBACK");
      return NextResponse.json({ error: "not eligible to review this submission" }, { status: 403 });
    }
    const s = sub.rows[0];
    if (s.status !== "PENDING") {
      await client.query("ROLLBACK");
      return NextResponse.json({ error: "submission already settled" }, { status: 409 });
    }

    // The CHECK constraint rejects self-votes, the unique key rejects double votes.
    await client.query(
      `INSERT INTO peer_reviews (submission_id, submitter_wallet, reviewer_wallet, vote_value)
       VALUES ($1, $2, $3, $4)`,
      [submissionId, s.wallet_address, parsed.wallet, vote],
    );

    const tally = await client.query<{ net: number }>(
      `SELECT COALESCE(SUM(vote_value), 0)::int AS net FROM peer_reviews WHERE submission_id = $1`,
      [submissionId],
    );
    const net = tally.rows[0].net;

    let status = "PENDING";
    if (net >= CONSENSUS_THRESHOLD) {
      status = "VERIFIED";
      await client.query(`UPDATE proof_submissions SET status = 'VERIFIED' WHERE id = $1`, [submissionId]);
      await client.query(
        `UPDATE pool_participants SET current_streak = LEAST(current_streak + 1, 21)
          WHERE pool_id = $1 AND wallet_address = $2`,
        [s.pool_id, s.wallet_address],
      );
    } else if (net <= -CONSENSUS_THRESHOLD) {
      status = "REJECTED";
      await client.query(`UPDATE proof_submissions SET status = 'REJECTED' WHERE id = $1`, [submissionId]);
      await client.query(
        `UPDATE pool_participants SET is_eliminated = TRUE, eliminated_on_day = $3
          WHERE pool_id = $1 AND wallet_address = $2 AND NOT is_eliminated`,
        [s.pool_id, s.wallet_address, s.day_number],
      );
    }

    await client.query("COMMIT");
    return NextResponse.json({ status, net }, { status: 201 });
  } catch (err) {
    await client.query("ROLLBACK").catch(() => {});
    const code = (err as { code?: string }).code;
    if (code === "23505") return NextResponse.json({ error: "already voted" }, { status: 409 });
    if (code === "23514") return NextResponse.json({ error: "cannot review your own proof" }, { status: 403 });
    throw err;
  } finally {
    client.release();
  }
}
