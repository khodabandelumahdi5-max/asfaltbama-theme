import type { Server as HttpServer } from "node:http";
import { Server } from "socket.io";
import { createAdapter } from "@socket.io/redis-adapter";
import {
  ROOMS,
  SPECTATOR_NAMESPACE,
  type Ack,
  type SessionSnapshot,
  type SpectatorSnapshot,
} from "@arena/shared";
import { config } from "../config";
import { devIdentity, validateInitData, type TelegramIdentity } from "../auth/telegram";
import { AppError, toAckError } from "../lib/errors";
import { logger } from "../logger";
import { market } from "../market/state";
import { redis, redisAdapterSub, redisPub } from "../redis/client";
import { keys } from "../redis/keys";
import { getTop } from "../leaderboard/leaderboard";
import { closeByUser, getAccount, getPosition, openPosition } from "../trading/engine";
import { joinTournament } from "../tournament/manager";
import { getActiveTournament } from "../tournament/store";
import { displayName, getUser, toProfile, upsertUser } from "../services/users";
import { bindRealtime, broadcast, type ArenaServer, type SpectatorNamespace } from "./bus";

interface SocketData {
  userId: string;
  name: string;
  bucket: { tokens: number; updatedAt: number };
}

const BUCKET_CAPACITY = 10;
const BUCKET_REFILL_PER_SEC = 5;

/** Token-bucket limiter per socket for state-changing actions. */
function consumeToken(data: SocketData): boolean {
  const now = Date.now();
  const elapsed = (now - data.bucket.updatedAt) / 1000;
  data.bucket.tokens = Math.min(BUCKET_CAPACITY, data.bucket.tokens + elapsed * BUCKET_REFILL_PER_SEC);
  data.bucket.updatedAt = now;
  if (data.bucket.tokens < 1) return false;
  data.bucket.tokens -= 1;
  return true;
}

async function handle<T>(cb: unknown, fn: () => Promise<T>): Promise<void> {
  if (typeof cb !== "function") return;
  const reply = cb as (res: Ack<T>) => void;
  try {
    reply({ ok: true, data: await fn() });
  } catch (err) {
    if (!(err instanceof AppError)) logger.error({ err }, "socket handler failed");
    reply(toAckError(err));
  }
}

export async function buildSessionSnapshot(userId: string): Promise<SessionSnapshot> {
  const user = getUser(userId);
  if (!user) throw new AppError("USER_NOT_FOUND", "User not found", 404);
  const tournament = await getActiveTournament();
  const [account, position, leaderboard] = tournament
    ? await Promise.all([getAccount(tournament.id, userId), getPosition(tournament.id, userId), getTop(tournament.id)])
    : [null, null, null];
  return {
    user: toProfile(user),
    tournament,
    account,
    position,
    leaderboard,
    candles: market.history(),
    price: market.price,
    serverTime: Date.now(),
  };
}

export async function buildSpectatorSnapshot(): Promise<SpectatorSnapshot> {
  const tournament = await getActiveTournament();
  return {
    tournament,
    leaderboard: tournament ? await getTop(tournament.id) : null,
    candles: market.history(),
    price: market.price,
    online: await onlineCount(),
    serverTime: Date.now(),
  };
}

let ioRef: ArenaServer | null = null;
let spectatorRef: SpectatorNamespace | null = null;

/** Publishes this instance's socket count; the leader aggregates it. */
async function heartbeatOnline(): Promise<void> {
  if (!ioRef) return;
  const local = ioRef.of("/").sockets.size + (spectatorRef?.sockets.size ?? 0);
  await redis.hset(keys.online, config.INSTANCE_ID, `${local}:${Date.now()}`);
}

export async function onlineCount(): Promise<number> {
  const all = await redis.hgetall(keys.online);
  const cutoff = Date.now() - 15_000;
  let total = 0;
  const stale: string[] = [];
  for (const [instance, value] of Object.entries(all)) {
    const [n, ts] = value.split(":").map(Number);
    if ((ts ?? 0) < cutoff) stale.push(instance);
    else total += n ?? 0;
  }
  if (stale.length) await redis.hdel(keys.online, ...stale);
  return total;
}

export async function broadcastOnline(): Promise<void> {
  broadcast("stats:online", await onlineCount());
}

export function createRealtime(httpServer: HttpServer): { io: ArenaServer; stop: () => Promise<void> } {
  const io: ArenaServer = new Server(httpServer, {
    path: "/socket.io",
    cors: { origin: config.CORS_ORIGINS, credentials: true },
    transports: ["websocket", "polling"],
    pingInterval: 20_000,
    pingTimeout: 20_000,
    maxHttpBufferSize: 64 * 1024,
    perMessageDeflate: false,
    connectionStateRecovery: { maxDisconnectionDuration: 60_000, skipMiddlewares: false },
    adapter: createAdapter(redisPub, redisAdapterSub, { key: "arena:sio" }),
  });
  const spectators: SpectatorNamespace = io.of(SPECTATOR_NAMESPACE);
  ioRef = io;
  spectatorRef = spectators;
  bindRealtime(io, spectators);

  io.use(async (socket, next) => {
    try {
      const auth = socket.handshake.auth as { initData?: unknown; devUser?: unknown };
      let identity: TelegramIdentity;
      if (typeof auth.initData === "string" && auth.initData.length > 0) identity = validateInitData(auth.initData);
      else if (auth.devUser) identity = devIdentity(auth.devUser);
      else throw new AppError("AUTH_REQUIRED", "initData required", 401);
      const user = await upsertUser(identity);
      const data = socket.data as SocketData;
      data.userId = user.id;
      data.name = displayName(user);
      data.bucket = { tokens: BUCKET_CAPACITY, updatedAt: Date.now() };
      next();
    } catch (err) {
      const e = err instanceof AppError ? err : new AppError("AUTH_FAILED", "Authentication failed", 401);
      if (!(err instanceof AppError)) logger.error({ err }, "socket auth error");
      const error = new Error(e.message) as Error & { data?: unknown };
      error.data = { code: e.code };
      next(error);
    }
  });

  io.on("connection", (socket) => {
    const data = socket.data as SocketData;
    void socket.join([ROOMS.arena, ROOMS.user(data.userId)]);

    const guarded = <T>(cb: unknown, fn: () => Promise<T>) =>
      handle(cb, async () => {
        if (!consumeToken(data)) throw new AppError("RATE_LIMITED", "Slow down", 429);
        return fn();
      });

    socket.on("session:sync", (cb) => void handle(cb, () => buildSessionSnapshot(data.userId)));
    socket.on("tournament:join", (cb) => void guarded(cb, () => joinTournament(data.userId)));
    socket.on("trade:open", (req, cb) => void guarded(cb, () => openPosition(data.userId, data.name, req)));
    socket.on("trade:close", (cb) => void guarded(cb, () => closeByUser(data.userId)));
    socket.on("disconnect", (reason) => logger.debug({ userId: data.userId, reason }, "socket disconnected"));
  });

  spectators.on("connection", (socket) => {
    socket.on("spectator:sync", (cb) => void handle(cb, () => buildSpectatorSnapshot()));
  });

  const heartbeat = setInterval(() => void heartbeatOnline().catch(() => undefined), 5_000);
  void heartbeatOnline().catch(() => undefined);

  return {
    io,
    stop: async () => {
      clearInterval(heartbeat);
      await redis.hdel(keys.online, config.INSTANCE_ID).catch(() => undefined);
      // Only this instance's sockets (`.local`): clients auto-reconnect and the load
      // balancer routes them to a healthy peer. The HTTP server itself is closed by Fastify.
      io.local.disconnectSockets(true);
      spectators.local.disconnectSockets(true);
    },
  };
}
