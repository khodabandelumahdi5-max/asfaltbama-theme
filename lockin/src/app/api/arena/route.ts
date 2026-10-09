import { NextResponse, type NextRequest } from "next/server";
import { db } from "@/lib/db";
import { walletAddress } from "@/lib/signed-request";
import type { ArenaParticipant, ArenaPool, ArenaState, ProofCardData } from "@/lib/types";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const REVIEW_QUEUE_LIMIT = 12;

const PROOF_COLUMNS = `
  s.id, s.wallet_address, s.day_number, s.video_cf_id, s.status, s.created_at,
  COUNT(r.id) FILTER (WHERE r.vote_value = 1)::int  AS approvals,
  COUNT(r.id) FILTER (WHERE r.vote_value = -1)::int AS rejections,
  MAX(r.vote_value) FILTER (WHERE r.reviewer_wallet = $2) AS my_vote`;

type ProofRow = {
  id: string;
  wallet_address: string;
  day_number: number;
  video_cf_id: string;
  status: ProofCardData["status"];
  created_at: Date;
  approvals: number;
  rejections: number;
  my_vote: number | null;
};

function toCard(r: ProofRow): ProofCardData {
  return {
    id: r.id,
    walletAddress: r.wallet_address,
    dayNumber: r.day_number,
    videoCfId: r.video_cf_id,
    status: r.status,
    createdAt: r.created_at.toISOString(),
    approvals: r.approvals,
    rejections: r.rejections,
    myVote: r.my_vote === 1 || r.my_vote === -1 ? r.my_vote : null,
  };
}

export async function GET(req: NextRequest) {
  const walletParam = req.nextUrl.searchParams.get("wallet");
  const wallet = walletParam && walletAddress.safeParse(walletParam).success ? walletParam : null;

  // Prefer the active pool this wallet is in; otherwise the soonest active pool.
  const poolRes = await db.query(
    `SELECT p.id, p.title, p.stake_lamports, p.start_date::text, p.end_date::text,
            p.total_locked_lamports, p.status,
            (CURRENT_DATE - p.start_date + 1) AS current_day,
            now() < (CURRENT_DATE::timestamp AT TIME ZONE 'UTC') + lockin_grace() AS in_grace,
            EXTRACT(EPOCH FROM lockin_grace())::int / 3600 AS grace_hours,
            (SELECT COUNT(*) FROM pool_participants pp WHERE pp.pool_id = p.id)::int AS participant_count,
            (SELECT COUNT(*) FROM pool_participants pp WHERE pp.pool_id = p.id AND pp.is_eliminated)::int AS eliminated_count
       FROM challenge_pools p
      WHERE p.status = 'ACTIVE'
      ORDER BY EXISTS (SELECT 1 FROM pool_participants pp
                        WHERE pp.pool_id = p.id AND pp.wallet_address = $1) DESC,
               p.start_date ASC
      LIMIT 1`,
    [wallet],
  );

  const empty: ArenaState = { pool: null, participant: null, myProofs: [], reviewQueue: [] };
  if (poolRes.rowCount === 0) return NextResponse.json(empty);

  const row = poolRes.rows[0];
  const currentDay = Math.max(0, Math.min(22, Number(row.current_day)));
  const graceDay = row.in_grace && currentDay - 1 >= 1 && currentDay - 1 <= 21 ? currentDay - 1 : null;
  const pool: ArenaPool = {
    id: row.id,
    title: row.title,
    stakeLamports: Number(row.stake_lamports),
    startDate: row.start_date,
    endDate: row.end_date,
    totalLockedLamports: Number(row.total_locked_lamports),
    status: row.status,
    participantCount: row.participant_count,
    eliminatedCount: row.eliminated_count,
    currentDay,
    graceDay,
    graceHours: row.grace_hours,
  };

  if (!wallet) return NextResponse.json({ ...empty, pool } satisfies ArenaState);

  const [partRes, mineRes, queueRes] = await Promise.all([
    db.query(
      `SELECT current_streak, is_eliminated, eliminated_on_day, elimination_reason, joined_at
         FROM pool_participants WHERE pool_id = $1 AND wallet_address = $2`,
      [pool.id, wallet],
    ),
    db.query<ProofRow>(
      `SELECT ${PROOF_COLUMNS}
         FROM proof_submissions s LEFT JOIN peer_reviews r ON r.submission_id = s.id
        WHERE s.pool_id = $1 AND s.wallet_address = $2
        GROUP BY s.id ORDER BY s.day_number`,
      [pool.id, wallet],
    ),
    db.query<ProofRow>(
      `SELECT ${PROOF_COLUMNS}
         FROM proof_submissions s LEFT JOIN peer_reviews r ON r.submission_id = s.id
        WHERE s.pool_id = $1 AND s.wallet_address <> $2 AND s.status = 'PENDING'
        GROUP BY s.id
        HAVING COUNT(r.id) FILTER (WHERE r.reviewer_wallet = $2) = 0
        ORDER BY s.created_at ASC
        LIMIT ${REVIEW_QUEUE_LIMIT}`,
      [pool.id, wallet],
    ),
  ]);

  const p = partRes.rows[0];
  const participant: ArenaParticipant | null = p
    ? {
        currentStreak: p.current_streak,
        isEliminated: p.is_eliminated,
        eliminatedOnDay: p.eliminated_on_day,
        eliminationReason: p.elimination_reason,
        joinedAt: p.joined_at.toISOString(),
      }
    : null;

  return NextResponse.json({
    pool,
    participant,
    myProofs: mineRes.rows.map(toCard),
    // Only participants get to review.
    reviewQueue: participant && !participant.isEliminated ? queueRes.rows.map(toCard) : [],
  } satisfies ArenaState);
}
