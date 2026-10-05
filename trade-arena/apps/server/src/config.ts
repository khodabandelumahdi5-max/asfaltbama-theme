import { z } from "zod";

const bool = z
  .union([z.boolean(), z.string()])
  .transform((v) => (typeof v === "boolean" ? v : ["1", "true", "yes", "on"].includes(v.toLowerCase())));

const schema = z.object({
  NODE_ENV: z.enum(["development", "production", "test"]).default("development"),
  SERVER_HOST: z.string().default("0.0.0.0"),
  SERVER_PORT: z.coerce.number().int().positive().default(4000),
  INSTANCE_ID: z.string().default(`${process.pid}-${Math.random().toString(36).slice(2, 8)}`),
  LOG_LEVEL: z.enum(["fatal", "error", "warn", "info", "debug", "trace"]).default("info"),

  REDIS_URL: z.string().url().default("redis://127.0.0.1:6379"),
  DATABASE_PATH: z.string().default("./data/arena.db"),
  CORS_ORIGINS: z
    .string()
    .default("http://localhost:3000,http://localhost:5174")
    .transform((s) => s.split(",").map((o) => o.trim()).filter(Boolean)),

  TELEGRAM_BOT_TOKEN: z.string().min(20),
  TELEGRAM_BOT_USERNAME: z.string().default("TradeArenaBot"),
  TELEGRAM_WEBAPP_SHORT_NAME: z.string().default("arena"),
  INIT_DATA_MAX_AGE_SEC: z.coerce.number().int().positive().default(86_400),
  ALLOW_DEV_AUTH: bool.default(false),
  INTERNAL_API_KEY: z.string().min(16),

  DEPOSIT_WEBHOOK_SECRET: z.string().min(16),
  DEPOSIT_ADDRESS_TRC20: z.string().min(10),
  DEPOSIT_ADDRESS_TON: z.string().min(10),
  DEPOSIT_TTL_SEC: z.coerce.number().int().positive().default(3_600),
  TICKET_PRICE_USDT: z.coerce.number().positive().default(1),
  REFERRAL_BONUS_TICKETS: z.coerce.number().int().nonnegative().default(1),
  WELCOME_TICKETS: z.coerce.number().int().nonnegative().default(3),

  TOURNAMENT_DURATION_SEC: z.coerce.number().int().min(30).default(600),
  TOURNAMENT_BREAK_SEC: z.coerce.number().int().min(5).default(30),
  TOURNAMENT_ENTRY_TICKETS: z.coerce.number().int().nonnegative().default(1),
  TOURNAMENT_PRIZE_SPLIT: z
    .string()
    .default("50,30,20")
    .transform((s) => s.split(",").map((n) => Number(n.trim())).filter((n) => Number.isFinite(n) && n > 0)),
  STARTING_BALANCE: z.coerce.number().positive().default(10_000),

  PRICE_SEED: z.coerce.number().positive().default(65_000),
  PRICE_TICK_MS: z.coerce.number().int().min(50).default(250),
  PRICE_ANNUAL_VOL: z.coerce.number().positive().default(0.9),
  CANDLE_INTERVAL_SEC: z.coerce.number().int().min(1).default(5),
  CANDLE_HISTORY: z.coerce.number().int().min(50).default(600),
  LEADERBOARD_FLUSH_MS: z.coerce.number().int().min(50).default(200),
});

const parsed = schema.safeParse(process.env);
if (!parsed.success) {
  console.error("Invalid server environment:", parsed.error.flatten().fieldErrors);
  process.exit(1);
}

export const config = parsed.data;
export const isProd = config.NODE_ENV === "production";
export const devAuthEnabled = config.ALLOW_DEV_AUTH && !isProd;
export type Config = typeof config;
