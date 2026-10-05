import { eq, sql } from "drizzle-orm";
import type { DB } from "../db/client";
import { ledger, users } from "../db/schema";
import { AppError } from "../lib/errors";

type Tx = Parameters<Parameters<DB["transaction"]>[0]>[0];
export type LedgerKind = typeof ledger.$inferInsert.kind;

/**
 * Applies a ticket delta inside an existing transaction. Rejects if the balance would go negative.
 * The (kind, refId, userId) unique index makes every credit/debit idempotent per reference.
 */
export function applyTickets(tx: Tx, userId: string, delta: number, kind: LedgerKind, refId: string | null): number {
  if (!Number.isInteger(delta) || delta === 0) throw new AppError("LEDGER_INVALID", "Ticket delta must be a non-zero integer");
  const updated = tx
    .update(users)
    .set({ tickets: sql`${users.tickets} + ${delta}` })
    .where(delta < 0 ? sql`${users.id} = ${userId} AND ${users.tickets} >= ${-delta}` : eq(users.id, userId))
    .returning({ tickets: users.tickets })
    .get();
  if (!updated) {
    throw delta < 0
      ? new AppError("INSUFFICIENT_TICKETS", "Not enough tickets. Deposit USDT to buy more.", 402)
      : new AppError("USER_NOT_FOUND", "User not found", 404);
  }
  tx.insert(ledger).values({ userId, delta, balanceAfter: updated.tickets, kind, refId }).run();
  return updated.tickets;
}
