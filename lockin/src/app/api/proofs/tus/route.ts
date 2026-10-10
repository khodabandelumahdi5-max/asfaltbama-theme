import { NextResponse, type NextRequest } from "next/server";
import { z } from "zod";
import { createTusUpload, streamConfig } from "@/lib/cloudflare";
import { db } from "@/lib/db";
import { parseSignedRequest } from "@/lib/signed-request";
import { MAX_VIDEO_BYTES, MAX_VIDEO_SECONDS } from "@/lib/video";

export const runtime = "nodejs";

/** Upload URLs a wallet may request per pool day (retries, re-records). */
const MAX_UPLOADS_PER_DAY = 5;
/** Cloudflare enforces the duration on its side; a little slack for container rounding. */
const CF_DURATION_SLACK_SECONDS = 5;

const payload = z.object({
  poolId: z.uuid(),
  dayNumber: z.number().int().min(1).max(21),
});

/**
 * TUS creation endpoint for proof videos. tus-js-client POSTs here with
 * Upload-Length plus our wallet-signed X-LockIn-* headers; we create the upload
 * at Cloudflare and answer with its one-time upload URL in Location. The video
 * bytes then go straight from the browser to Cloudflare, never through us.
 */
export async function POST(req: NextRequest) {
  const h = req.headers;
  const parsed = parseSignedRequest(
    {
      wallet: h.get("x-lockin-wallet"),
      issuedAt: Number(h.get("x-lockin-issued-at")),
      signature: h.get("x-lockin-signature"),
      poolId: h.get("x-lockin-pool-id"),
      dayNumber: Number(h.get("x-lockin-day")),
    },
    "upload-video",
    payload,
  );
  if (!parsed.ok) return NextResponse.json({ error: parsed.error }, { status: 400 });

  const uploadLength = Number(h.get("upload-length"));
  if (!Number.isSafeInteger(uploadLength) || uploadLength <= 0) {
    return NextResponse.json({ error: "Upload-Length required" }, { status: 400 });
  }
  if (uploadLength > MAX_VIDEO_BYTES) {
    return NextResponse.json({ error: `video larger than ${MAX_VIDEO_BYTES / 1024 / 1024} MiB` }, { status: 413 });
  }
  if (!streamConfig()) return NextResponse.json({ error: "video uploads not configured" }, { status: 503 });

  const { poolId, dayNumber } = parsed.data;
  const eligible = await db.query<{ uploads: number }>(
    `SELECT (SELECT COUNT(*) FROM video_uploads v
              WHERE v.pool_id = $1 AND v.wallet_address = $2 AND v.day_number = $3)::int AS uploads
       FROM pool_participants pp
       JOIN challenge_pools p ON p.id = pp.pool_id
      WHERE pp.pool_id = $1 AND pp.wallet_address = $2 AND NOT pp.is_eliminated
        AND p.status = 'ACTIVE' AND lockin_day_is_open(p.start_date, $3)
        AND NOT EXISTS (SELECT 1 FROM proof_submissions s
                         WHERE s.pool_id = $1 AND s.wallet_address = $2 AND s.day_number = $3)`,
    [poolId, parsed.wallet, dayNumber],
  );
  if (eligible.rowCount === 0) {
    return NextResponse.json({ error: "no proof is due for this day" }, { status: 403 });
  }
  if (eligible.rows[0].uploads >= MAX_UPLOADS_PER_DAY) {
    return NextResponse.json({ error: "too many uploads for this day" }, { status: 429 });
  }

  let upload: { location: string; uid: string };
  try {
    upload = await createTusUpload({
      uploadLength,
      name: `lockin ${poolId} day ${dayNumber} ${parsed.wallet}`,
      creator: parsed.wallet,
      maxDurationSeconds: MAX_VIDEO_SECONDS + CF_DURATION_SLACK_SECONDS,
    });
  } catch (err) {
    console.error("[proofs/tus]", err);
    return NextResponse.json({ error: "could not start upload" }, { status: 502 });
  }

  await db.query(
    `INSERT INTO video_uploads (uid, wallet_address, pool_id, day_number, upload_length) VALUES ($1, $2, $3, $4, $5)`,
    [upload.uid, parsed.wallet, poolId, dayNumber, uploadLength],
  );

  return new NextResponse(null, {
    status: 201,
    headers: {
      Location: upload.location,
      "Tus-Resumable": "1.0.0",
      "stream-media-id": upload.uid,
      "Access-Control-Expose-Headers": "Location, stream-media-id",
    },
  });
}
