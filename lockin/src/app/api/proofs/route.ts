import { NextResponse, type NextRequest } from "next/server";
import { z } from "zod";
import { CHALLENGE_DAYS } from "@/lib/constants";
import { db } from "@/lib/db";
import { parseSignedRequest } from "@/lib/signed-request";

export const runtime = "nodejs";

const proofPayload = z.object({
  poolId: z.uuid(),
  dayNumber: z.number().int().min(1).max(CHALLENGE_DAYS),
  videoCfId: z.string().regex(/^[a-f0-9]{32}$/), // Cloudflare Stream video UID
});

export async function POST(req: NextRequest) {
  const parsed = parseSignedRequest(await req.json().catch(() => null), "submit-proof", proofPayload);
  if (!parsed.ok) return NextResponse.json({ error: parsed.error }, { status: 400 });

  const { poolId, dayNumber, videoCfId } = parsed.data;
  try {
    const res = await db.query(
      `INSERT INTO proof_submissions (pool_id, wallet_address, day_number, video_cf_id)
       VALUES ($1, $2, $3, $4)
       RETURNING id`,
      [poolId, parsed.wallet, dayNumber, videoCfId],
    );
    return NextResponse.json({ id: res.rows[0].id }, { status: 201 });
  } catch (err) {
    const pgErr = err as { code?: string; message?: string };
    // 23505 unique, 23503 not a participant, P0001 raised by the day/elimination trigger.
    if (pgErr.code === "23505") return NextResponse.json({ error: "already submitted" }, { status: 409 });
    if (pgErr.code === "23503") return NextResponse.json({ error: "not a participant" }, { status: 403 });
    if (pgErr.code === "P0001") return NextResponse.json({ error: pgErr.message }, { status: 422 });
    throw err;
  }
}
