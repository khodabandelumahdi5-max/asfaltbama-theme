import "server-only";
import { z } from "zod";
import { verifyWalletSignature } from "./auth";
import { buildSignedMessage, canonicalPayload } from "./signed-message";

export const walletAddress = z.string().regex(/^[1-9A-HJ-NP-Za-km-z]{32,44}$/);

const envelope = z.object({
  wallet: walletAddress,
  issuedAt: z.number().int(),
  signature: z.string().min(64).max(100),
});

/**
 * Parses `{ wallet, issuedAt, signature, ...payload }` and verifies that the
 * wallet signed `action` + the canonical JSON of the payload fields.
 */
export function parseSignedRequest<T extends z.ZodRawShape>(
  body: unknown,
  action: string,
  payload: z.ZodObject<T>,
): { ok: true; wallet: string; data: z.infer<z.ZodObject<T>> } | { ok: false; error: string } {
  const env = envelope.safeParse(body);
  const data = payload.safeParse(body);
  if (!env.success || !data.success) return { ok: false, error: "invalid request" };

  const message = buildSignedMessage(action, canonicalPayload(data.data), env.data.issuedAt);
  const valid = verifyWalletSignature({
    wallet: env.data.wallet,
    message,
    signature: env.data.signature,
    issuedAt: env.data.issuedAt,
  });
  return valid ? { ok: true, wallet: env.data.wallet, data: data.data } : { ok: false, error: "bad signature" };
}
