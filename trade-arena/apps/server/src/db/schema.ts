import { sql } from "drizzle-orm";
import { index, integer, real, sqliteTable, text, uniqueIndex } from "drizzle-orm/sqlite-core";

const createdAt = () =>
  integer("created_at", { mode: "number" })
    .notNull()
    .default(sql`(unixepoch('subsec') * 1000)`);

export const users = sqliteTable(
  "users",
  {
    id: text("id").primaryKey(), // Telegram user id (string to stay safe beyond 2^53)
    username: text("username"),
    firstName: text("first_name").notNull(),
    lastName: text("last_name"),
    languageCode: text("language_code"),
    photoUrl: text("photo_url"),
    isPremium: integer("is_premium", { mode: "boolean" }).notNull().default(false),
    referralCode: text("referral_code").notNull(),
    referredBy: text("referred_by"),
    tickets: integer("tickets").notNull().default(0),
    lastSeenAt: integer("last_seen_at", { mode: "number" }).notNull(),
    createdAt: createdAt(),
  },
  (t) => [uniqueIndex("users_referral_code_uq").on(t.referralCode), index("users_referred_by_idx").on(t.referredBy)],
);

/** Append-only ticket ledger. Sum(delta) per user always equals users.tickets. */
export const ledger = sqliteTable(
  "ledger",
  {
    id: integer("id").primaryKey({ autoIncrement: true }),
    userId: text("user_id")
      .notNull()
      .references(() => users.id),
    delta: integer("delta").notNull(),
    balanceAfter: integer("balance_after").notNull(),
    kind: text("kind", {
      enum: ["WELCOME", "DEPOSIT", "REFERRAL_BONUS", "TOURNAMENT_ENTRY", "TOURNAMENT_PRIZE", "ADJUSTMENT"],
    }).notNull(),
    refId: text("ref_id"),
    createdAt: createdAt(),
  },
  (t) => [
    index("ledger_user_idx").on(t.userId, t.createdAt),
    uniqueIndex("ledger_kind_ref_user_uq").on(t.kind, t.refId, t.userId),
  ],
);

export const tournaments = sqliteTable(
  "tournaments",
  {
    id: text("id").primaryKey(),
    name: text("name").notNull(),
    status: text("status", { enum: ["SCHEDULED", "ACTIVE", "SETTLING", "FINISHED"] }).notNull(),
    startsAt: integer("starts_at", { mode: "number" }).notNull(),
    endsAt: integer("ends_at", { mode: "number" }).notNull(),
    entryTickets: integer("entry_tickets").notNull(),
    startingBalance: real("starting_balance").notNull(),
    players: integer("players").notNull().default(0),
    prizePoolTickets: integer("prize_pool_tickets").notNull().default(0),
    finalPrice: real("final_price"),
    finishedAt: integer("finished_at", { mode: "number" }),
    createdAt: createdAt(),
  },
  (t) => [index("tournaments_status_idx").on(t.status, t.endsAt)],
);

export const tournamentEntries = sqliteTable(
  "tournament_entries",
  {
    id: integer("id").primaryKey({ autoIncrement: true }),
    tournamentId: text("tournament_id")
      .notNull()
      .references(() => tournaments.id),
    userId: text("user_id")
      .notNull()
      .references(() => users.id),
    startingBalance: real("starting_balance").notNull(),
    finalBalance: real("final_balance"),
    finalPnlPct: real("final_pnl_pct"),
    finalRank: integer("final_rank"),
    prizeTickets: integer("prize_tickets").notNull().default(0),
    trades: integer("trades").notNull().default(0),
    joinedAt: createdAt(),
  },
  (t) => [
    uniqueIndex("entries_tournament_user_uq").on(t.tournamentId, t.userId),
    index("entries_user_idx").on(t.userId),
  ],
);

export const trades = sqliteTable(
  "trades",
  {
    id: text("id").primaryKey(),
    tournamentId: text("tournament_id")
      .notNull()
      .references(() => tournaments.id),
    userId: text("user_id")
      .notNull()
      .references(() => users.id),
    symbol: text("symbol").notNull(),
    side: text("side", { enum: ["LONG", "SHORT"] }).notNull(),
    leverage: integer("leverage").notNull(),
    margin: real("margin").notNull(),
    qty: real("qty").notNull(),
    entryPrice: real("entry_price").notNull(),
    liquidationPrice: real("liquidation_price").notNull(),
    openFee: real("open_fee").notNull(),
    exitPrice: real("exit_price"),
    closeFee: real("close_fee"),
    realizedPnl: real("realized_pnl"),
    closeReason: text("close_reason", { enum: ["MANUAL", "LIQUIDATION", "TOURNAMENT_END"] }),
    status: text("status", { enum: ["OPEN", "CLOSED"] }).notNull(),
    openedAt: integer("opened_at", { mode: "number" }).notNull(),
    closedAt: integer("closed_at", { mode: "number" }),
  },
  (t) => [index("trades_user_idx").on(t.userId, t.openedAt), index("trades_tournament_idx").on(t.tournamentId, t.status)],
);

export const deposits = sqliteTable(
  "deposits",
  {
    id: text("id").primaryKey(),
    reference: text("reference").notNull(),
    userId: text("user_id")
      .notNull()
      .references(() => users.id),
    network: text("network", { enum: ["TRC20", "TON"] }).notNull(),
    asset: text("asset", { enum: ["USDT"] }).notNull().default("USDT"),
    address: text("address").notNull(),
    /** Expected amount in micro-USDT (1e-6). */
    amountMicro: integer("amount_micro").notNull(),
    receivedMicro: integer("received_micro"),
    tickets: integer("tickets").notNull(),
    status: text("status", { enum: ["PENDING", "CONFIRMED", "EXPIRED", "UNDERPAID"] }).notNull(),
    txHash: text("tx_hash"),
    expiresAt: integer("expires_at", { mode: "number" }).notNull(),
    confirmedAt: integer("confirmed_at", { mode: "number" }),
    createdAt: createdAt(),
  },
  (t) => [
    uniqueIndex("deposits_reference_uq").on(t.reference),
    uniqueIndex("deposits_tx_hash_uq").on(t.txHash),
    uniqueIndex("deposits_pending_amount_uq")
      .on(t.network, t.amountMicro)
      .where(sql`status = 'PENDING'`),
    index("deposits_user_idx").on(t.userId, t.createdAt),
  ],
);

export type User = typeof users.$inferSelect;
export type Tournament = typeof tournaments.$inferSelect;
export type Deposit = typeof deposits.$inferSelect;
export type Trade = typeof trades.$inferSelect;
