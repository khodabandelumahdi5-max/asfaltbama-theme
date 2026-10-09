// Verifies, from a fetched transaction, how many lamports `from` sent to `to`
// through System Program transfers (top-level and inner/CPI instructions).
// Used by the payout executor before refunding a deposit, so a refund can only
// ever return lamports that provably arrived in escrow.
import { PublicKey, SystemInstruction, SystemProgram, TransactionInstruction } from "@solana/web3.js";

export interface DecodedInstruction {
  programId: PublicKey;
  accounts: PublicKey[];
  data: Uint8Array;
}

export function sumSystemTransfers(instructions: DecodedInstruction[], from: string, to: string): bigint {
  let total = 0n;
  for (const ix of instructions) {
    if (!ix.programId.equals(SystemProgram.programId)) continue;
    const tix = new TransactionInstruction({
      programId: ix.programId,
      keys: ix.accounts.map((pubkey) => ({ pubkey, isSigner: false, isWritable: true })),
      data: Buffer.from(ix.data),
    });
    let type: string;
    try {
      type = SystemInstruction.decodeInstructionType(tix);
    } catch {
      continue;
    }
    if (type !== "Transfer") continue;
    const t = SystemInstruction.decodeTransfer(tix);
    if (t.fromPubkey.toBase58() === from && t.toPubkey.toBase58() === to) total += BigInt(t.lamports);
  }
  return total;
}

export type DepositCheck =
  | { ok: true }
  | { ok: false; retryLater: true; reason: string }
  | { ok: false; retryLater: false; reason: string };

/**
 * Decides whether a recorded deposit may be refunded.
 * `found` is the finalized transaction (or null if the RPC has no finalized copy).
 * A missing transaction is retried while the deposit is young (it may simply not be
 * finalized yet) and held once it is older than `missingAfterMs`.
 */
export function checkDeposit(params: {
  found: { err: unknown; transferred: bigint } | null;
  expectedLamports: bigint;
  receivedAt: Date;
  now: Date;
  missingAfterMs: number;
}): DepositCheck {
  const { found, expectedLamports } = params;
  if (!found) {
    const age = params.now.getTime() - params.receivedAt.getTime();
    return age < params.missingAfterMs
      ? { ok: false, retryLater: true, reason: "deposit not finalized yet" }
      : { ok: false, retryLater: false, reason: "deposit transaction not found on chain" };
  }
  if (found.err) return { ok: false, retryLater: false, reason: `deposit transaction failed on chain: ${JSON.stringify(found.err)}` };
  if (found.transferred !== expectedLamports) {
    return {
      ok: false,
      retryLater: false,
      reason: `on-chain transfer to escrow is ${found.transferred} lamports, recorded ${expectedLamports}`,
    };
  }
  return { ok: true };
}
