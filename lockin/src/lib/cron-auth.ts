import "server-only";
import { NextResponse, type NextRequest } from "next/server";
import { headerMatches } from "./secret-header";

/**
 * Cron endpoints require `Authorization: Bearer <CRON_SECRET>`. That is also
 * the header Vercel Cron sends when CRON_SECRET is set on the project.
 * Fails closed: if the secret is missing or too short, nothing gets through.
 */
export function rejectUnlessCron(req: NextRequest): NextResponse | null {
  const secret = process.env.CRON_SECRET;
  if (!secret || secret.length < 16) {
    console.error("[cron] CRON_SECRET is not configured (min 16 chars); refusing request");
    return NextResponse.json({ error: "cron not configured" }, { status: 503 });
  }
  if (!headerMatches(req.headers.get("authorization"), `Bearer ${secret}`)) {
    return NextResponse.json({ error: "unauthorized" }, { status: 401 });
  }
  return null;
}
