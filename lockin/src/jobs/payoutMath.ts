// Pure settlement arithmetic. All amounts are lamports as bigint: pools can exceed
// Number.MAX_SAFE_INTEGER only in theory, but floats must never touch money.

export const PLATFORM_FEE_BPS = 1000n; // 10%
const BPS_DENOMINATOR = 10_000n;

export interface SettlementSplit {
  total: bigint;
  platformFee: bigint;
  /** Remainder of the integer split; goes to the platform so totals reconcile exactly. */
  dust: bigint;
  survivorCount: number;
  perSurvivor: bigint;
}

/**
 * fee = floor(total * 10%), perSurvivor = floor((total - fee) / survivors).
 * With zero survivors nobody can be paid, so the whole pool goes to the platform.
 */
export function computeSettlement(total: bigint, survivorCount: number): SettlementSplit {
  if (total < 0n) throw new RangeError("total must be non-negative");
  if (!Number.isInteger(survivorCount) || survivorCount < 0) {
    throw new RangeError("survivorCount must be a non-negative integer");
  }

  const platformFee = (total * PLATFORM_FEE_BPS) / BPS_DENOMINATOR;
  const distributable = total - platformFee;

  if (survivorCount === 0) {
    return { total, platformFee, dust: distributable, survivorCount, perSurvivor: 0n };
  }

  const n = BigInt(survivorCount);
  const perSurvivor = distributable / n;
  return { total, platformFee, dust: distributable - perSurvivor * n, survivorCount, perSurvivor };
}

export function chunk<T>(items: readonly T[], size: number): T[][] {
  if (size < 1) throw new RangeError("size must be >= 1");
  const out: T[][] = [];
  for (let i = 0; i < items.length; i += size) out.push(items.slice(i, i + size));
  return out;
}
