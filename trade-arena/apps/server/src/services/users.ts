import { count, eq } from "drizzle-orm";
import type { UserProfile } from "@arena/shared";
import type { TelegramIdentity } from "../auth/telegram";
import { config } from "../config";
import { db } from "../db/client";
import { users, type User } from "../db/schema";
import { referralCode } from "../lib/ids";
import { keys } from "../redis/keys";
import { redis } from "../redis/client";
import { applyTickets } from "./ledger";

const REF_PREFIX = "ref_";

export function parseReferral(startParam: string | null | undefined): string | null {
  if (!startParam || !startParam.startsWith(REF_PREFIX)) return null;
  const code = startParam.slice(REF_PREFIX.length).toUpperCase();
  return /^[0-9A-Z]{4,16}$/.test(code) ? code : null;
}

export function displayName(u: Pick<User, "username" | "firstName">): string {
  return u.username ? `@${u.username}` : u.firstName;
}

/**
 * Creates the user on first contact (with referral attribution and welcome tickets),
 * or refreshes profile fields on subsequent logins. Referral is only ever set once.
 */
export async function upsertUser(identity: TelegramIdentity, referral: string | null = parseReferral(identity.startParam)): Promise<User> {
  const now = Date.now();
  const user = db.transaction((tx) => {
    const existing = tx.select().from(users).where(eq(users.id, identity.id)).get();
    if (existing) {
      return tx
        .update(users)
        .set({
          username: identity.username,
          firstName: identity.firstName,
          lastName: identity.lastName,
          languageCode: identity.languageCode,
          isPremium: identity.isPremium,
          photoUrl: identity.photoUrl ?? existing.photoUrl,
          lastSeenAt: now,
        })
        .where(eq(users.id, identity.id))
        .returning()
        .get()!;
    }

    let referredBy: string | null = null;
    if (referral) {
      const referrer = tx.select({ id: users.id }).from(users).where(eq(users.referralCode, referral)).get();
      if (referrer && referrer.id !== identity.id) referredBy = referrer.id;
    }

    let code = referralCode();
    while (tx.select({ id: users.id }).from(users).where(eq(users.referralCode, code)).get()) code = referralCode();

    tx.insert(users)
      .values({
        id: identity.id,
        username: identity.username,
        firstName: identity.firstName,
        lastName: identity.lastName,
        languageCode: identity.languageCode,
        isPremium: identity.isPremium,
        photoUrl: identity.photoUrl,
        referralCode: code,
        referredBy,
        tickets: 0,
        lastSeenAt: now,
      })
      .run();
    if (config.WELCOME_TICKETS > 0) applyTickets(tx, identity.id, config.WELCOME_TICKETS, "WELCOME", identity.id);
    return tx.select().from(users).where(eq(users.id, identity.id)).get()!;
  });

  await redis.hset(keys.userNames, user.id, displayName(user));
  return user;
}

export function getUser(id: string): User | undefined {
  return db.select().from(users).where(eq(users.id, id)).get();
}

export function toProfile(user: User): UserProfile {
  const referrals = db.select({ n: count() }).from(users).where(eq(users.referredBy, user.id)).get();
  return {
    id: user.id,
    username: user.username,
    firstName: user.firstName,
    photoUrl: user.photoUrl,
    referralCode: user.referralCode,
    tickets: user.tickets,
    referralCount: referrals?.n ?? 0,
  };
}

export function referralLink(user: Pick<User, "referralCode">): string {
  return `https://t.me/${config.TELEGRAM_BOT_USERNAME}?start=${REF_PREFIX}${user.referralCode}`;
}

export function miniAppLink(user: Pick<User, "referralCode">): string {
  return `https://t.me/${config.TELEGRAM_BOT_USERNAME}/${config.TELEGRAM_WEBAPP_SHORT_NAME}?startapp=${REF_PREFIX}${user.referralCode}`;
}
