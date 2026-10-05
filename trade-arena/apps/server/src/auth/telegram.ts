import { createHmac, timingSafeEqual } from "node:crypto";
import { z } from "zod";
import { config, devAuthEnabled } from "../config";
import { AppError } from "../lib/errors";

const telegramUserSchema = z.object({
  id: z.number().int().positive(),
  first_name: z.string().default(""),
  last_name: z.string().optional(),
  username: z.string().optional(),
  language_code: z.string().optional(),
  is_premium: z.boolean().optional(),
  photo_url: z.string().url().optional(),
});

export interface TelegramIdentity {
  id: string;
  firstName: string;
  lastName: string | null;
  username: string | null;
  languageCode: string | null;
  isPremium: boolean;
  photoUrl: string | null;
  startParam: string | null;
}

// secret_key = HMAC_SHA256(key="WebAppData", message=bot_token)
const secretKey = createHmac("sha256", "WebAppData").update(config.TELEGRAM_BOT_TOKEN).digest();

/**
 * Validates Telegram Mini App initData per
 * https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
export function validateInitData(initData: string, maxAgeSec = config.INIT_DATA_MAX_AGE_SEC): TelegramIdentity {
  if (!initData || initData.length > 4096) throw new AppError("AUTH_INVALID", "Missing or malformed initData", 401);

  const params = new URLSearchParams(initData);
  const hash = params.get("hash");
  if (!hash || !/^[a-f0-9]{64}$/.test(hash)) throw new AppError("AUTH_INVALID", "initData hash missing", 401);
  params.delete("hash");

  const dataCheckString = [...params.entries()]
    .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0))
    .map(([k, v]) => `${k}=${v}`)
    .join("\n");

  const expected = createHmac("sha256", secretKey).update(dataCheckString).digest();
  const received = Buffer.from(hash, "hex");
  if (received.length !== expected.length || !timingSafeEqual(received, expected)) {
    throw new AppError("AUTH_INVALID", "initData signature mismatch", 401);
  }

  const authDate = Number(params.get("auth_date"));
  if (!Number.isFinite(authDate)) throw new AppError("AUTH_INVALID", "auth_date missing", 401);
  const age = Math.floor(Date.now() / 1000) - authDate;
  if (age > maxAgeSec) throw new AppError("AUTH_EXPIRED", "initData expired, reopen the app", 401);

  const rawUser = params.get("user");
  if (!rawUser) throw new AppError("AUTH_INVALID", "user missing in initData", 401);
  let parsedJson: unknown;
  try {
    parsedJson = JSON.parse(rawUser);
  } catch {
    throw new AppError("AUTH_INVALID", "user payload malformed", 401);
  }
  const user = telegramUserSchema.safeParse(parsedJson);
  if (!user.success) throw new AppError("AUTH_INVALID", "user payload malformed", 401);

  return {
    id: String(user.data.id),
    firstName: user.data.first_name || user.data.username || "Trader",
    lastName: user.data.last_name ?? null,
    username: user.data.username ?? null,
    languageCode: user.data.language_code ?? null,
    isPremium: user.data.is_premium ?? false,
    photoUrl: user.data.photo_url ?? null,
    startParam: params.get("start_param"),
  };
}

const devUserSchema = z.object({
  id: z.string().regex(/^dev_[a-z0-9]{6,32}$/),
  username: z.string().min(2).max(32),
});

/** Development-only identity for running the webapp in a normal browser outside Telegram. */
export function devIdentity(raw: unknown): TelegramIdentity {
  if (!devAuthEnabled) throw new AppError("AUTH_INVALID", "Dev auth disabled", 401);
  const parsed = devUserSchema.safeParse(raw);
  if (!parsed.success) throw new AppError("AUTH_INVALID", "Invalid dev user", 401);
  return {
    id: parsed.data.id,
    firstName: parsed.data.username,
    lastName: null,
    username: parsed.data.username,
    languageCode: "en",
    isPremium: false,
    photoUrl: null,
    startParam: null,
  };
}

/** Resolves identity from an `Authorization: tma <initData>` or `Authorization: dev <json>` header. */
export function identityFromAuthHeader(header: string | undefined): TelegramIdentity {
  if (!header) throw new AppError("AUTH_REQUIRED", "Authorization header required", 401);
  const space = header.indexOf(" ");
  const scheme = space > 0 ? header.slice(0, space).toLowerCase() : "";
  const value = space > 0 ? header.slice(space + 1) : "";
  if (scheme === "tma") return validateInitData(value);
  if (scheme === "dev") {
    try {
      return devIdentity(JSON.parse(value));
    } catch (err) {
      if (err instanceof AppError) throw err;
      throw new AppError("AUTH_INVALID", "Invalid dev user", 401);
    }
  }
  throw new AppError("AUTH_INVALID", "Unsupported authorization scheme", 401);
}
