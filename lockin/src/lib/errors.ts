import { SOLANA_CLUSTER } from "./constants";

/**
 * Human-readable message for a caught error. Wallet adapter errors often have an
 * empty `message` and carry the real cause in `.error`.
 */
export function errorMessage(e: unknown, fallback: string): string {
  if (!(e instanceof Error)) return fallback;
  const cause = (e as { error?: unknown }).error;
  const causeMessage = cause instanceof Error ? cause.message : typeof cause === "string" ? cause : "";
  if (e.name === "WalletSignTransactionError" || e.name === "WalletSignMessageError") {
    if (/reject|denied|cancel/i.test(e.message || causeMessage)) return "Request cancelled in wallet.";
  }
  if (e.name === "WalletSendTransactionError" && !e.message && !causeMessage) {
    // Raised without detail when the wallet account doesn't support the app's network.
    return `Your wallet couldn't send the transaction. Make sure it is connected to Solana ${SOLANA_CLUSTER}.`;
  }
  return e.message || causeMessage || fallback;
}
