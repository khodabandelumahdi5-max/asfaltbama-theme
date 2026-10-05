import { randomUUID } from "node:crypto";
import { and, count, eq, sql } from "drizzle-orm";
import { round, type AccountView, type TournamentResult, type TournamentView } from "@arena/shared";
import { config } from "../config";
import { db } from "../db/client";
import { ledger, tournamentEntries, tournaments, users } from "../db/schema";
import { AppError } from "../lib/errors";
import { logger } from "../logger";
import { market } from "../market/state";
import { redis } from "../redis/client";
import { keys } from "../redis/keys";
import { broadcast, emitToUser, publishFeed } from "../realtime/bus";
import { notifyTelegram } from "../realtime/notify";
import { applyTickets } from "../services/ledger";
import { closePosition, getAccount, openPositionHolders } from "../trading/engine";
import {
  getActiveTournament,
  getActiveTournamentId,
  getTournamentView,
  setTournamentStatus,
  writeTournamentMeta,
} from "./store";

const ARCHIVE_TTL_SEC = 3_600;

/**
 * Joins the current (scheduled or live) tournament. The ticket debit and entry row are
 * committed atomically in SQLite first; the Redis account is created idempotently after,
 * so a retry after a partial failure never double-charges.
 */
export async function joinTournament(userId: string): Promise<{ account: AccountView; tickets: number }> {
  const t = await getActiveTournament();
  if (!t || (t.status !== "SCHEDULED" && t.status !== "ACTIVE")) {
    throw new AppError("TOURNAMENT_CLOSED", "Registration is closed, next round starts soon", 409);
  }

  const { tickets, isNew } = db.transaction((tx) => {
    const existing = tx
      .select({ id: tournamentEntries.id })
      .from(tournamentEntries)
      .where(and(eq(tournamentEntries.tournamentId, t.id), eq(tournamentEntries.userId, userId)))
      .get();
    if (existing) {
      const u = tx.select({ tickets: users.tickets }).from(users).where(eq(users.id, userId)).get();
      return { tickets: u?.tickets ?? 0, isNew: false };
    }
    let remaining = 0;
    if (t.entryTickets > 0) remaining = applyTickets(tx, userId, -t.entryTickets, "TOURNAMENT_ENTRY", t.id);
    else remaining = tx.select({ tickets: users.tickets }).from(users).where(eq(users.id, userId)).get()?.tickets ?? 0;
    tx.insert(tournamentEntries).values({ tournamentId: t.id, userId, startingBalance: t.startingBalance }).run();
    tx.update(tournaments)
      .set({
        players: sql`${tournaments.players} + 1`,
        prizePoolTickets: sql`${tournaments.prizePoolTickets} + ${t.entryTickets}`,
      })
      .where(eq(tournaments.id, t.id))
      .run();
    return { tickets: remaining, isNew: true };
  });

  const joined = await redis.joinTournament(
    keys.tAccount(t.id, userId),
    keys.tLeaderboard(t.id),
    keys.tPlayers(t.id),
    keys.tLeaderboardDirty(t.id),
    userId,
    String(t.startingBalance),
  );
  if (isNew && joined === 1) {
    await redis
      .multi()
      .hincrby(keys.tMeta(t.id), "players", 1)
      .hincrby(keys.tMeta(t.id), "prizePool", t.entryTickets)
      .exec();
    const updated = await getTournamentView(t.id);
    if (updated) broadcast("tournament:state", updated);
  }
  emitToUser(userId, "wallet:update", { tickets });
  const account = (await getAccount(t.id, userId))!;
  return { account, tickets };
}

/**
 * Leader-only scheduler. Rolling rounds: SCHEDULED (registration) → ACTIVE (trading)
 * → SETTLING (force-close + payouts) → FINISHED, then the next round is scheduled.
 * Every transition is idempotent so a new leader can resume a half-finished settlement.
 */
export class TournamentScheduler {
  private timer: NodeJS.Timeout | null = null;
  private busy = false;
  private unsubscribeTick: (() => void) | null = null;

  constructor(private readonly onTickWhileActive: (tid: string, price: number) => Promise<void>) {}

  start(): void {
    if (this.timer) return;
    this.timer = setInterval(() => void this.run(), 500);
    void this.run();
    let sweeping = false;
    this.unsubscribeTick = market.onTick((tick) => {
      if (sweeping) return;
      sweeping = true;
      getActiveTournament()
        .then((t) => (t && t.status === "ACTIVE" ? this.onTickWhileActive(t.id, tick.price) : undefined))
        .catch((err) => logger.error({ err }, "tick handler failed"))
        .finally(() => {
          sweeping = false;
        });
    });
  }

  stop(): void {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
    this.unsubscribeTick?.();
    this.unsubscribeTick = null;
  }

  private async run(): Promise<void> {
    if (this.busy) return;
    this.busy = true;
    try {
      const t = await getActiveTournament();
      const now = Date.now();
      if (!t || t.status === "FINISHED") {
        await this.schedule(now + (t ? config.TOURNAMENT_BREAK_SEC * 1000 : 5_000));
      } else if (t.status === "SCHEDULED" && now >= t.startsAt) {
        await this.transition(t, "ACTIVE");
      } else if (t.status === "ACTIVE" && now >= t.endsAt) {
        await this.settle(t);
      } else if (t.status === "SETTLING") {
        await this.settle(t);
      }
    } catch (err) {
      logger.error({ err }, "tournament scheduler failed");
    } finally {
      this.busy = false;
    }
  }

  private async transition(t: TournamentView, status: TournamentView["status"]): Promise<TournamentView> {
    db.update(tournaments).set({ status }).where(eq(tournaments.id, t.id)).run();
    await setTournamentStatus(t.id, status);
    const next = { ...t, status };
    broadcast("tournament:state", next);
    logger.info({ tournamentId: t.id, status }, "tournament transition");
    return next;
  }

  private async schedule(startsAt: number): Promise<void> {
    // Resume a scheduled/active round persisted in SQLite if Redis lost its pointer (e.g. Redis restart).
    const persisted = db
      .select()
      .from(tournaments)
      .where(sql`${tournaments.status} IN ('SCHEDULED','ACTIVE','SETTLING')`)
      .orderBy(sql`${tournaments.startsAt} DESC`)
      .get();
    if (persisted) {
      const view: TournamentView = {
        id: persisted.id,
        name: persisted.name,
        status: persisted.status,
        startsAt: persisted.startsAt,
        endsAt: persisted.endsAt,
        entryTickets: persisted.entryTickets,
        startingBalance: persisted.startingBalance,
        players: persisted.players,
        prizePoolTickets: persisted.prizePoolTickets,
      };
      await writeTournamentMeta(view);
      await redis.set(keys.activeTournament, view.id);
      broadcast("tournament:state", view);
      return;
    }

    const seq = (db.select({ n: count() }).from(tournaments).get()?.n ?? 0) + 1;
    const view: TournamentView = {
      id: randomUUID(),
      name: `Blitz #${seq}`,
      status: "SCHEDULED",
      startsAt,
      endsAt: startsAt + config.TOURNAMENT_DURATION_SEC * 1000,
      entryTickets: config.TOURNAMENT_ENTRY_TICKETS,
      startingBalance: config.STARTING_BALANCE,
      players: 0,
      prizePoolTickets: 0,
    };
    db.insert(tournaments)
      .values({
        id: view.id,
        name: view.name,
        status: view.status,
        startsAt: view.startsAt,
        endsAt: view.endsAt,
        entryTickets: view.entryTickets,
        startingBalance: view.startingBalance,
      })
      .run();
    await writeTournamentMeta(view);
    await redis.set(keys.activeTournament, view.id);
    broadcast("tournament:state", view);
    logger.info({ tournamentId: view.id, tournament: view.name, startsAt }, "tournament scheduled");
  }

  private async settle(t: TournamentView): Promise<void> {
    if (t.status !== "SETTLING") t = await this.transition(t, "SETTLING");
    const finalPrice = market.price;

    // 1. Force-close every open position at the final mark price.
    const holders = await openPositionHolders(t.id);
    await Promise.allSettled(holders.map((uid) => closePosition(t.id, uid, "TOURNAMENT_END", finalPrice)));

    // 2. Final standings straight from the sorted set.
    const raw = await redis.zrevrange(keys.tLeaderboard(t.id), 0, -1, "WITHSCORES");
    const standings: Array<{ userId: string; pnlPct: number; balance: number; trades: number }> = [];
    for (let i = 0; i < raw.length; i += 2) {
      const userId = raw[i]!;
      const acct = await redis.hmget(keys.tAccount(t.id, userId), "bal", "trades");
      standings.push({
        userId,
        pnlPct: Number(raw[i + 1]),
        balance: Number(acct[0] ?? t.startingBalance),
        trades: Number(acct[1] ?? 0),
      });
    }

    // 3. Prize split (in tickets) for the top finishers who actually traded with positive PnL.
    const persisted = db.select().from(tournaments).where(eq(tournaments.id, t.id)).get();
    const pool = persisted?.prizePoolTickets ?? t.prizePoolTickets;
    const eligible = standings.filter((s) => s.trades > 0 && s.pnlPct > 0);
    const prizes = new Map<string, number>();
    config.TOURNAMENT_PRIZE_SPLIT.forEach((pct, idx) => {
      const winner = eligible[idx];
      const amount = Math.floor((pool * pct) / 100);
      if (winner && amount > 0) prizes.set(winner.userId, amount);
    });

    const finishedAt = Date.now();
    db.transaction((tx) => {
      standings.forEach((s, idx) => {
        const prize = prizes.get(s.userId) ?? 0;
        tx.update(tournamentEntries)
          .set({
            finalBalance: s.balance,
            finalPnlPct: s.pnlPct,
            finalRank: idx + 1,
            prizeTickets: prize,
            trades: s.trades,
          })
          .where(and(eq(tournamentEntries.tournamentId, t.id), eq(tournamentEntries.userId, s.userId)))
          .run();
        if (prize > 0) {
          const already = tx
            .select({ id: ledger.id })
            .from(ledger)
            .where(and(eq(ledger.kind, "TOURNAMENT_PRIZE"), eq(ledger.refId, t.id), eq(ledger.userId, s.userId)))
            .get();
          if (!already) applyTickets(tx, s.userId, prize, "TOURNAMENT_PRIZE", t.id);
        }
      });
      tx.update(tournaments)
        .set({ status: "FINISHED", finishedAt, finalPrice, players: standings.length })
        .where(eq(tournaments.id, t.id))
        .run();
    });
    await setTournamentStatus(t.id, "FINISHED");

    // 4. Announce.
    const names = standings.length ? await redis.hmget(keys.userNames, ...standings.slice(0, 10).map((s) => s.userId)) : [];
    const result: TournamentResult = {
      tournamentId: t.id,
      name: t.name,
      finishedAt,
      winners: standings.slice(0, 3).map((s, i) => ({
        rank: i + 1,
        userId: s.userId,
        name: names[i] ?? "Trader",
        pnlPct: round(s.pnlPct, 4),
        prizeTickets: prizes.get(s.userId) ?? 0,
      })),
    };
    broadcast("tournament:state", { ...t, status: "FINISHED" });
    broadcast("tournament:result", result);
    for (const w of result.winners) {
      publishFeed({ kind: "WINNER", name: w.name, pnl: w.pnlPct });
      const prizeText = w.prizeTickets > 0 ? ` You won ${w.prizeTickets} 🎟 tickets!` : "";
      await notifyTelegram(
        w.userId,
        `🏆 ${t.name} finished — you placed #${w.rank} with ${w.pnlPct >= 0 ? "+" : ""}${w.pnlPct.toFixed(2)}%.${prizeText}`,
      );
    }
    for (const [userId] of prizes) {
      const tickets = db.select({ tickets: users.tickets }).from(users).where(eq(users.id, userId)).get();
      if (tickets) emitToUser(userId, "wallet:update", { tickets: tickets.tickets });
    }

    // 5. Archive Redis state (kept 1h for late reads), then the scheduler creates the next round.
    const players = await redis.smembers(keys.tPlayers(t.id));
    const pipe = redis.pipeline();
    for (const uid of players) pipe.expire(keys.tAccount(t.id, uid), ARCHIVE_TTL_SEC);
    for (const k of [keys.tMeta(t.id), keys.tLeaderboard(t.id), keys.tPlayers(t.id), keys.tLiqLong(t.id), keys.tLiqShort(t.id)]) {
      pipe.expire(k, ARCHIVE_TTL_SEC);
    }
    pipe.del(keys.tLeaderboardDirty(t.id));
    await pipe.exec();
    logger.info({ tournamentId: t.id, players: standings.length, pool, prizes: Object.fromEntries(prizes) }, "tournament settled");
  }
}

export async function currentTournamentId(): Promise<string | null> {
  return getActiveTournamentId();
}
