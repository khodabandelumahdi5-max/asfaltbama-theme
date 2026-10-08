import { z } from "zod";

const schema = z.object({
  NODE_ENV: z.enum(["development", "production", "test"]).default("development"),
  TELEGRAM_BOT_TOKEN: z.string().min(20),
  TELEGRAM_BOT_USERNAME: z.string().default("TradeArenaBot"),
  /** Public HTTPS URL of the Mini App. Optional: without it the bot runs chat-only (no Open Arena buttons). */
  WEBAPP_URL: z
    .string()
    .optional()
    .transform((u) => (u ? u.trim() : undefined))
    .refine((u) => !u || /^https:\/\/[^\s]+$/.test(u), "WEBAPP_URL must be an HTTPS URL (Telegram requirement)"),
  SERVER_INTERNAL_URL: z.string().url().default("http://127.0.0.1:4000"),
  INTERNAL_API_KEY: z.string().min(16),
  REDIS_URL: z.string().url().default("redis://127.0.0.1:6379"),
  BOT_MODE: z.enum(["polling", "webhook"]).default("polling"),
  BOT_WEBHOOK_DOMAIN: z.string().optional(),
  BOT_WEBHOOK_PORT: z.coerce.number().int().positive().default(4100),
  BOT_WEBHOOK_SECRET: z.string().optional(),
  TICKET_PRICE_USDT: z.coerce.number().positive().default(1),
});

const parsed = schema.safeParse(process.env);
if (!parsed.success) {
  console.error("Invalid bot environment:", parsed.error.flatten().fieldErrors);
  process.exit(1);
}
export const config = parsed.data;

if (config.BOT_MODE === "webhook" && !config.BOT_WEBHOOK_DOMAIN) {
  console.error("BOT_WEBHOOK_DOMAIN is required when BOT_MODE=webhook");
  process.exit(1);
}
