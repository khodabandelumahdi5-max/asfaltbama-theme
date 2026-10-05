import type { FastifyInstance } from "fastify";
import { AppError } from "../../lib/errors";
import { handleDepositWebhook, verifyWebhookSignature, webhookSchema } from "../../services/payments";

/**
 * Deposit confirmation webhook, called by a chain watcher (TronGrid / TonAPI indexer,
 * or the mock script in scripts/mock-deposit.ts). Signed with HMAC-SHA256.
 */
export async function webhookRoutes(app: FastifyInstance): Promise<void> {
  app.post("/api/webhooks/deposits", { config: { rateLimit: { max: 600, timeWindow: "1 minute" } } }, async (req) => {
    const raw = req.rawBody;
    if (typeof raw !== "string") throw new AppError("WEBHOOK_BODY", "JSON body required", 400);
    verifyWebhookSignature(raw, req.headers["x-timestamp"] as string | undefined, req.headers["x-signature"] as string | undefined);
    const parsed = webhookSchema.safeParse(req.body);
    if (!parsed.success) throw new AppError("WEBHOOK_INVALID", parsed.error.issues[0]?.message ?? "Invalid payload", 422);
    return handleDepositWebhook(parsed.data);
  });
}
