import "server-only";
import bs58 from "bs58";
import nacl from "tweetnacl";

const MAX_AGE_MS = 5 * 60 * 1000;

/**
 * Verifies a wallet-signed request. The client signs
 * `buildSignedMessage(action, payload, issuedAt)` with `signMessage`; the
 * server rebuilds the same string, so the signature binds wallet, action,
 * payload and time. Replays inside the window are stopped by DB unique keys.
 */
export function verifyWalletSignature(params: {
  wallet: string;
  message: string;
  signature: string;
  issuedAt: number;
}): boolean {
  const age = Date.now() - params.issuedAt;
  if (!Number.isFinite(age) || age < -30_000 || age > MAX_AGE_MS) return false;

  try {
    return nacl.sign.detached.verify(
      new TextEncoder().encode(params.message),
      bs58.decode(params.signature),
      bs58.decode(params.wallet),
    );
  } catch {
    return false;
  }
}
