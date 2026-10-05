import { NOTIFY_CHANNEL } from "@arena/shared";
import { redisPub } from "../redis/client";

/** Queues a Telegram DM; the bot process consumes NOTIFY_CHANNEL and delivers it. */
export async function notifyTelegram(userId: string, text: string): Promise<void> {
  if (!/^\d+$/.test(userId)) return; // dev users have no Telegram chat
  await redisPub.publish(NOTIFY_CHANNEL, JSON.stringify({ userId, text })).catch(() => undefined);
}
