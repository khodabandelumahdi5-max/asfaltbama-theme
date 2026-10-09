import { NextResponse, type NextRequest } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { parseSignedRequest } from "@/lib/signed-request";

export const runtime = "nodejs";

const payload = z.object({ poolId: z.uuid() });

/** Issues a one-time Cloudflare Stream direct-upload URL to an active participant. */
export async function POST(req: NextRequest) {
  const parsed = parseSignedRequest(await req.json().catch(() => null), "upload-url", payload);
  if (!parsed.ok) return NextResponse.json({ error: parsed.error }, { status: 400 });

  const member = await db.query(
    `SELECT 1 FROM pool_participants
      WHERE pool_id = $1 AND wallet_address = $2 AND NOT is_eliminated`,
    [parsed.data.poolId, parsed.wallet],
  );
  if (member.rowCount === 0) return NextResponse.json({ error: "not an active participant" }, { status: 403 });

  const account = process.env.CF_ACCOUNT_ID;
  const token = process.env.CF_STREAM_API_TOKEN;
  if (!account || !token) return NextResponse.json({ error: "video uploads not configured" }, { status: 503 });

  const cf = await fetch(`https://api.cloudflare.com/client/v4/accounts/${account}/stream/direct_upload`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      maxDurationSeconds: 120,
      expiry: new Date(Date.now() + 30 * 60 * 1000).toISOString(),
      meta: { wallet: parsed.wallet, poolId: parsed.data.poolId },
    }),
  });
  const json = (await cf.json()) as { success: boolean; result?: { uploadURL: string; uid: string } };
  if (!cf.ok || !json.success || !json.result) {
    return NextResponse.json({ error: "cloudflare upload url failed" }, { status: 502 });
  }
  return NextResponse.json({ uploadURL: json.result.uploadURL, uid: json.result.uid });
}
