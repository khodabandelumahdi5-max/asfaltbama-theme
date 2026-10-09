import { NextResponse, type NextRequest } from "next/server";
import { settleDuePools } from "@/jobs/settlePool";
import { rejectUnlessCron } from "@/lib/cron-auth";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

/**
 * Settles every pool whose final deadline has passed: final elimination pass,
 * 10% platform fee, equal split among survivors, payout batches, status SETTLED.
 * Already-settled pools are skipped, so re-running is safe.
 */
async function handler(req: NextRequest) {
  const denied = rejectUnlessCron(req);
  if (denied) return denied;

  try {
    const result = await settleDuePools();
    // 500 when any pool failed so cron monitoring alerts; settled pools are still committed.
    return NextResponse.json(result, { status: result.failed.length > 0 ? 500 : 200 });
  } catch (err) {
    console.error("[cron/settle-pool] failed", err);
    return NextResponse.json({ error: err instanceof Error ? err.message : "settlement failed" }, { status: 500 });
  }
}

export { handler as GET, handler as POST };
