import { z } from "zod";

// Subset of a Helius "enhanced" webhook transaction we rely on.
// https://docs.helius.dev/webhooks-and-websockets/webhooks
const nativeTransfer = z.object({
  fromUserAccount: z.string().nullable(),
  toUserAccount: z.string().nullable(),
  amount: z.number().int().nonnegative(),
});

const enhancedTransaction = z.object({
  signature: z.string().min(64).max(88),
  slot: z.number().int().nonnegative(),
  timestamp: z.number().int().optional(),
  type: z.string().optional(),
  transactionError: z.unknown().nullable().optional(),
  nativeTransfers: z.array(nativeTransfer).default([]),
});

export const heliusPayload = z.array(enhancedTransaction);
export type HeliusTransaction = z.infer<typeof enhancedTransaction>;

export interface DepositCandidate {
  txSig: string;
  slot: number;
  fromWallet: string;
  lamports: number;
}

/**
 * Collapse each successful transaction into one deposit per sender: the total
 * lamports that sender moved into the treasury. Failed transactions and
 * transfers to other accounts are ignored.
 */
export function extractDeposits(
  txs: HeliusTransaction[],
  treasury: string,
): DepositCandidate[] {
  const deposits: DepositCandidate[] = [];

  for (const tx of txs) {
    if (tx.transactionError) continue;

    const bySender = new Map<string, number>();
    for (const t of tx.nativeTransfers) {
      if (t.toUserAccount !== treasury || !t.fromUserAccount) continue;
      if (t.fromUserAccount === treasury) continue;
      bySender.set(t.fromUserAccount, (bySender.get(t.fromUserAccount) ?? 0) + t.amount);
    }

    // One stake per transaction: a tx funded by several wallets is ambiguous,
    // so only the first sender is credited and the rest fall out as no-ops.
    const first = bySender.entries().next();
    if (first.done) continue;
    const [fromWallet, lamports] = first.value;
    deposits.push({ txSig: tx.signature, slot: tx.slot, fromWallet, lamports });
  }

  return deposits;
}
