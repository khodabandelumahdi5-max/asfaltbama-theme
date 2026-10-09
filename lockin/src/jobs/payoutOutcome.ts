// Pure decision logic for the payout executor (scripts/execute-payouts.ts).
// No I/O, so it can be unit-tested without a cluster.

export type AttemptOutcome = "CONFIRMED" | "FAILED" | "EXPIRED" | "IN_FLIGHT";

export interface AttemptObservation {
  /** getSignatureStatuses(..., { searchTransactionHistory: true }) result, or null if unknown. */
  status: { confirmationStatus?: string | null; err: unknown } | null;
  /** Finalized block height, read BEFORE the signature status (see below). */
  finalizedBlockHeight: number;
  /** lastValidBlockHeight of the blockhash the transaction was signed with. */
  lastValidBlockHeight: number;
}

/**
 * Decides what happened to a signed payout transaction.
 *
 * - Landed and finalized without error -> CONFIRMED (money moved).
 * - Landed and finalized with an error -> FAILED (atomic: no money moved).
 * - Unknown to the cluster after the finalized height passed the blockhash's
 *   last valid height -> EXPIRED. It can never land, so a new transaction is safe.
 * - Anything else -> IN_FLIGHT: keep waiting; never sign a replacement.
 *
 * The expiry rule is only sound if the block height was read first. A transaction
 * included at or below lastValidBlockHeight is in a finalized block once the
 * finalized height exceeds it, so a status lookup made afterwards must find it.
 */
export function classifyAttempt(o: AttemptObservation): AttemptOutcome {
  if (o.status) {
    if (o.status.confirmationStatus !== "finalized") return "IN_FLIGHT";
    return o.status.err ? "FAILED" : "CONFIRMED";
  }
  return o.finalizedBlockHeight > o.lastValidBlockHeight ? "EXPIRED" : "IN_FLIGHT";
}

export const GENESIS_HASH = {
  mainnet: "5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d",
  devnet: "EtWTRABZaYq6iMfeYKouRu166VU2xqa1wcaWoxPkrZBG",
  testnet: "4uhcVJyU9pJkvQyS88uRDiswHXSCkY3zQawwpjk2NsNY",
} as const;

export function clusterName(genesisHash: string): string {
  const hit = Object.entries(GENESIS_HASH).find(([, h]) => h === genesisHash);
  return hit ? hit[0] : "localnet/unknown";
}

/**
 * A system account must end at exactly 0 lamports or at least the rent-exempt
 * minimum; anything in between makes the runtime reject the transaction.
 */
export function leavesValidBalance(balance: bigint, outflow: bigint, rentExemptMin: bigint): boolean {
  const after = balance - outflow;
  return after === 0n || after >= rentExemptMin;
}
