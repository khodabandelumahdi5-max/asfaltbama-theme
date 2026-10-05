import Fastify, { type FastifyInstance } from "fastify";
import cors from "@fastify/cors";
import helmet from "@fastify/helmet";
import rateLimit from "@fastify/rate-limit";
import { ZodError } from "zod";
import { config } from "../config";
import { AppError } from "../lib/errors";
import { loggerOptions } from "../logger";
import { redis } from "../redis/client";
import { market } from "../market/state";
import { internalRoutes } from "./routes/internal";
import { publicRoutes } from "./routes/public";
import { webhookRoutes } from "./routes/webhooks";

declare module "fastify" {
  interface FastifyRequest {
    rawBody?: string;
  }
}

export interface Health {
  ready: boolean;
  draining: boolean;
  isLeader: () => boolean;
}

export async function buildApp(health: Health): Promise<FastifyInstance> {
  const app = Fastify({
    logger: loggerOptions,
    trustProxy: true,
    bodyLimit: 64 * 1024,
    requestIdHeader: "x-request-id",
  });

  // Keep the exact raw body (needed for webhook HMAC verification) while still parsing JSON.
  app.addContentTypeParser("application/json", { parseAs: "string" }, (req, body, done) => {
    const raw = body as string;
    req.rawBody = raw;
    if (raw.length === 0) return done(null, undefined);
    try {
      done(null, JSON.parse(raw));
    } catch {
      done(new AppError("INVALID_JSON", "Malformed JSON body", 400), undefined);
    }
  });

  await app.register(helmet, { contentSecurityPolicy: false, crossOriginResourcePolicy: { policy: "cross-origin" } });
  await app.register(cors, {
    origin: config.CORS_ORIGINS,
    credentials: true,
    methods: ["GET", "POST", "OPTIONS"],
    allowedHeaders: ["authorization", "content-type", "x-request-id"],
  });
  await app.register(rateLimit, {
    global: true,
    max: 300,
    timeWindow: "1 minute",
    redis,
    nameSpace: "arena:rl:",
    skipOnError: true,
    allowList: (req) => req.url.startsWith("/health") || req.url.startsWith("/internal"),
  });

  app.setErrorHandler((err, req, reply) => {
    if (err instanceof AppError) return reply.code(err.statusCode).send({ error: err.message, code: err.code });
    if (err instanceof ZodError) {
      return reply.code(400).send({ error: err.issues[0]?.message ?? "Invalid request", code: "VALIDATION" });
    }
    const status = (err as { statusCode?: number }).statusCode ?? 500;
    if (status >= 500) req.log.error({ err }, "request failed");
    return reply.code(status).send({ error: status >= 500 ? "Internal error" : (err as Error).message, code: "ERROR" });
  });

  app.get("/health/live", async () => ({ ok: true }));
  app.get("/health/ready", async (_req, reply) => {
    let redisOk = false;
    try {
      redisOk = (await redis.ping()) === "PONG";
    } catch {
      redisOk = false;
    }
    const ok = health.ready && !health.draining && redisOk;
    reply.code(ok ? 200 : 503);
    return {
      ok,
      draining: health.draining,
      redis: redisOk,
      leader: health.isLeader(),
      marketFresh: market.isFresh(),
      price: market.price,
      instance: config.INSTANCE_ID,
    };
  });

  await app.register(publicRoutes);
  await app.register(webhookRoutes);
  await app.register(internalRoutes);
  return app;
}
