import { randomBytes, randomInt } from "node:crypto";

/** Crockford base32 without ambiguous characters (I, L, O, U). */
const ALPHABET = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";

export function randomCode(length: number): string {
  const bytes = randomBytes(length);
  let out = "";
  for (let i = 0; i < length; i++) out += ALPHABET[bytes[i]! & 31];
  return out;
}

/** Deposit reference such as `TA-7KQ2-M9XD`. 40 bits of entropy. */
export function depositReference(): string {
  const c = randomCode(8);
  return `TA-${c.slice(0, 4)}-${c.slice(4)}`;
}

export function referralCode(): string {
  return randomCode(7);
}

/** Unique micro-amount tag (1..9999 millionths) so TRC20 transfers without memo can be matched. */
export function amountTag(): number {
  return randomInt(1, 10_000);
}
