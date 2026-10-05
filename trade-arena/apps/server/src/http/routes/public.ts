import type { FastifyInstance } from "fastify";
import { and, desc, eq, inArray, isNotNull } from "drizzle-orm";
import { z } from "zod";
import { db } from "../../db/client";
import { tournamentEntries, tournaments, trades, users } from "../../db/schema";
import { getTop } from "../../leaderboard/leaderboard";
import { market } from "../../market/state";
import { getActiveTournament } from "../../tournament/store";
import { createDeposit, listDeposits } from "../../services/payments";
import { displayName, miniAppLink, referralLink, toProfile } from "../../services/users";
import { buildSessionSnapshot } from "../../realtime/socket";
import { requireUser } from "../auth";

export async function publicRoutes(app: FastifyInstance): Promise<void> {
  app.get("/api/me", async (req) => {
    const user = await requireUser(req);
    return { user: toProfile(user), referralLink: referralLink(user), miniAppLink: miniAppLink(user) };
  });

  app.get("/api/session", async (req) => {
    const user = await requireUser(req);
    return buildSessionSnapshot(user.id);
  });

  app.get("/api/me/trades", async (req) => {
    const user = await requireUser(req);
    const { limit } = z.object({ limit: z.coerce.number().int().min(1).max(100).default(30) }).parse(req.query);
    return db.select().from(trades).where(eq(trades.userId, user.id)).orderBy(desc(trades.openedAt)).limit(limit).all();
  });

  app.get("/api/market/candles", async () => ({ price: market.price, candles: market.history() }));

  app.get("/api/tournaments/current", async () => {
    const tournament = await getActiveTournament();
    return { tournament, leaderboard: tournament ? await getTop(tournament.id) : null };
  });

  app.get("/api/leaderboard/:tournamentId", async (req) => {
    const { tournamentId } = z.object({ tournamentId: z.string().uuid() }).parse(req.params);
    const { limit } = z.object({ limit: z.coerce.number().int().min(1).max(100).default(10) }).parse(req.query);
    return getTop(tournamentId, limit);
  });

  app.get("/api/tournaments/history", async (req) => {
    const { limit } = z.object({ limit: z.coerce.number().int().min(1).max(50).default(10) }).parse(req.query);
    const finished = db
      .select()
      .from(tournaments)
      .where(eq(tournaments.status, "FINISHED"))
      .orderBy(desc(tournaments.finishedAt))
      .limit(limit)
      .all();
    if (finished.length === 0) return [];
    const podium = db
      .select({
        tournamentId: tournamentEntries.tournamentId,
        rank: tournamentEntries.finalRank,
        pnlPct: tournamentEntries.finalPnlPct,
        prizeTickets: tournamentEntries.prizeTickets,
        username: users.username,
        firstName: users.firstName,
      })
      .from(tournamentEntries)
      .innerJoin(users, eq(users.id, tournamentEntries.userId))
      .where(
        and(
          inArray(tournamentEntries.tournamentId, finished.map((t) => t.id)),
          isNotNull(tournamentEntries.finalRank),
        ),
      )
      .all()
      .filter((e) => (e.rank ?? 99) <= 3);
    return finished.map((t) => ({
      ...t,
      podium: podium
        .filter((p) => p.tournamentId === t.id)
        .sort((a, b) => (a.rank ?? 0) - (b.rank ?? 0))
        .map((p) => ({ rank: p.rank, name: displayName(p), pnlPct: p.pnlPct, prizeTickets: p.prizeTickets })),
    }));
  });

  app.post("/api/payments/deposits", async (req, reply) => {
    const user = await requireUser(req);
    reply.code(201);
    return createDeposit(user.id, req.body);
  });

  app.get("/api/payments/deposits", async (req) => {
    const user = await requireUser(req);
    return listDeposits(user.id);
  });
}
