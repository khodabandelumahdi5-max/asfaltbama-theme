import { timingSafeEqual } from "node:crypto";
import type { FastifyRequest } from "fastify";
import { identityFromAuthHeader } from "../auth/telegram";
import { config } from "../config";
import type { User } from "../db/schema";
import { AppError } from "../lib/errors";
import { upsertUser } from "../services/users";

/** Resolves the caller from `Authorization: tma <initData>` and returns the persisted user. */
export async function requireUser(req: FastifyRequest): Promise<User> {
  const identity = identityFromAuthHeader(req.headers.authorization);
  return upsertUser(identity);
}

const internalKey = Buffer.from(config.INTERNAL_API_KEY);

export function requireInternal(req: FastifyRequest): void {
  const given = Buffer.from(String(req.headers["x-internal-key"] ?? ""));
  if (given.length !== internalKey.length || !timingSafeEqual(given, internalKey)) {
    throw new AppError("FORBIDDEN", "Invalid internal key", 403);
  }
}
