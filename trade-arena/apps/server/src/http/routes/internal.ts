import type { FastifyInstance } from "fastify";
import { z } from "zod";
import { AppError } from "../../lib/errors";
import { createDeposit } from "../../services/payments";
import { getUser, miniAppLink, parseReferral, referralLink, toProfile, upsertUser } from "../../services/users";
import { getTop } from "../../leaderboard/leaderboard";
import { getAccount } from "../../trading/engine";
import { getActiveTournament } from "../../tournament/store";
import { requireInternal } from "../auth";

const upsertSchema = z.object({
  id: z.union([z.string().regex(/^\d+$/), z.number().int().positive()]).transform(String),
  firstName: z.string().min(1).max(128),
  lastName: z.string().max(128).nullish(),
  username: z.string().max(64).nullish(),
  languageCode: z.string().max(16).nullish(),
  isPremium: z.boolean().optional(),
  startPayload: z.string().max(64).nullish(),
});

/** Bot → server API (shared secret). Never exposed through the public ingress. */
export async function internalRoutes(app: FastifyInstance): Promise<void> {
  app.addHook("onRequest", async (req) => requireInternal(req));

  app.post("/internal/users/upsert", async (req) => {
    const body = upsertSchema.parse(req.body);
    const user = await upsertUser(
      {
        id: body.id,
        firstName: body.firstName,
        lastName: body.lastName ?? null,
        username: body.username ?? null,
        languageCode: body.languageCode ?? null,
        isPremium: body.isPremium ?? false,
        photoUrl: null,
        startParam: body.startPayload ?? null,
      },
      parseReferral(body.startPayload),
    );
    return { user: toProfile(user), referralLink: referralLink(user), miniAppLink: miniAppLink(user) };
  });

  app.get("/internal/users/:id/summary", async (req) => {
    const { id } = z.object({ id: z.string().regex(/^\d+$/) }).parse(req.params);
    const user = getUser(id);
    if (!user) throw new AppError("USER_NOT_FOUND", "User not found", 404);
    const tournament = await getActiveTournament();
    const [account, leaderboard] = tournament
      ? await Promise.all([getAccount(tournament.id, id), getTop(tournament.id)])
      : [null, null];
    return { user: toProfile(user), referralLink: referralLink(user), tournament, account, leaderboard };
  });

  app.post("/internal/deposits", async (req, reply) => {
    const body = z
      .object({ userId: z.string().regex(/^\d+$/), network: z.enum(["TRC20", "TON"]), tickets: z.number().int() })
      .parse(req.body);
    if (!getUser(body.userId)) throw new AppError("USER_NOT_FOUND", "User not found", 404);
    reply.code(201);
    return createDeposit(body.userId, { network: body.network, tickets: body.tickets });
  });
}
