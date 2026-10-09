import "server-only";
import { timingSafeEqual } from "node:crypto";

/** Constant-time comparison of a request header against an expected value. */
export function headerMatches(actual: string | null, expected: string): boolean {
  if (!actual) return false;
  const a = Buffer.from(actual);
  const b = Buffer.from(expected);
  return a.length === b.length && timingSafeEqual(a, b);
}
