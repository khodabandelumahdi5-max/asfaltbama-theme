import { Redis } from "ioredis";
import type { Telegram } from "telegraf";
import { NOTIFY_CHANNEL } from "@arena/shared";
import { config } from "./config";

interface Notification {
  userId: string;
  text: string;
}

const MAX_PER_SECOND = 25; // Telegram global limit is ~30 msg/s per bot

/**
 * Consumes server-originated notifications (deposit credited, tournament results,
 * referral bonus) from Redis pub/sub and delivers them as DMs with a global rate limit.
 */
export function startNotifier(telegram: Telegram): () => Promise<void> {
  const sub = new Redis(config.REDIS_URL, { maxRetriesPerRequest: null, connectionName: "arena:bot-notify" });
  const queue: Notification[] = [];
  sub.on("error", (err) => console.error("[notifier] redis error", err.message));
  void sub.subscribe(NOTIFY_CHANNEL);
  sub.on("message", (channel, message) => {
    if (channel !== NOTIFY_CHANNEL) return;
    try {
      const n = JSON.parse(message) as Notification;
      if (/^\d+$/.test(n.userId) && typeof n.text === "string") queue.push(n);
    } catch {
      /* ignore malformed */
    }
  });

  const timer = setInterval(() => {
    const batch = queue.splice(0, MAX_PER_SECOND);
    for (const n of batch) {
      telegram.sendMessage(n.userId, n.text, { link_preview_options: { is_disabled: true } }).catch((err: unknown) => {
        const code = (err as { response?: { error_code?: number; parameters?: { retry_after?: number } } }).response;
        if (code?.error_code === 429) {
          queue.unshift(n); // retry later
        } else if (code?.error_code !== 403) {
          console.error("[notifier] send failed", (err as Error).message);
        }
      });
    }
  }, 1_000);

  return async () => {
    clearInterval(timer);
    await sub.quit().catch(() => undefined);
  };
}
