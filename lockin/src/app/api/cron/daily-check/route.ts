import { NextResponse, type NextRequest } from "next/server";
import { runDailyElimination } from "@/jobs/dailyElimination";
import { rejectUnlessCron } from "@/lib/cron-auth";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

/** Eliminates participants with a missed deadline and closes finished pools. Idempotent. */
async function handler(req: NextRequest) {
  const denied = rejectUnlessCron(req);
  if (denied) return denied;

  try {
    return NextResponse.json(await runDailyElimination());
  } catch (err) {
    console.error("[cron/daily-check] failed", err);
    return NextResponse.json({ error: "daily check failed" }, { status: 500 });
  }
}

// Vercel Cron issues GET; POST is accepted for other schedulers.
export { handler as GET, handler as POST };
