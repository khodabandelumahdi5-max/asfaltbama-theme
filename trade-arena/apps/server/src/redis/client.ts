import { Redis, type RedisOptions } from "ioredis";
import { config } from "../config";
import { logger } from "../logger";
import {
  CLOSE_POSITION_LUA,
  JOIN_TOURNAMENT_LUA,
  OPEN_POSITION_LUA,
  RELEASE_LOCK_LUA,
  RENEW_LOCK_LUA,
} from "./scripts";

type LuaResult = Array<string> | null;

export interface ArenaRedis extends Redis {
  openPosition(
    acct: string, pos: string, liqLong: string, liqShort: string, meta: string,
    uid: string, tradeId: string, side: string, leverage: string, margin: string,
    price: string, feeRate: string, mmr: string, now: string,
  ): Promise<LuaResult>;
  closePosition(
    acct: string, pos: string, liqLong: string, liqShort: string, lb: string, lbDirty: string,
    uid: string, exitPrice: string, feeRate: string, reason: string,
  ): Promise<LuaResult>;
  joinTournament(acct: string, lb: string, players: string, lbDirty: string, uid: string, startingBalance: string): Promise<number>;
  renewLock(lock: string, instanceId: string, ttlMs: string): Promise<number>;
  releaseLock(lock: string, instanceId: string): Promise<number>;
}

const baseOptions: RedisOptions = {
  maxRetriesPerRequest: 3,
  enableAutoPipelining: true,
  retryStrategy: (times) => Math.min(times * 200, 3_000),
  reconnectOnError: (err) => err.message.startsWith("READONLY"),
};

function create(name: string, opts: RedisOptions = {}): ArenaRedis {
  const client = new Redis(config.REDIS_URL, { ...baseOptions, connectionName: `arena:${name}`, ...opts }) as ArenaRedis;
  client.on("error", (err) => logger.error({ err, client: name }, "redis error"));
  client.defineCommand("openPosition", { numberOfKeys: 5, lua: OPEN_POSITION_LUA });
  client.defineCommand("closePosition", { numberOfKeys: 6, lua: CLOSE_POSITION_LUA });
  client.defineCommand("joinTournament", { numberOfKeys: 4, lua: JOIN_TOURNAMENT_LUA });
  client.defineCommand("renewLock", { numberOfKeys: 1, lua: RENEW_LOCK_LUA });
  client.defineCommand("releaseLock", { numberOfKeys: 1, lua: RELEASE_LOCK_LUA });
  return client;
}

/** Command connection. */
export const redis = create("cmd");
/** Socket.io adapter + domain pub/sub. Subscriber connections cannot issue normal commands. */
export const redisPub = create("pub");
export const redisAdapterSub = create("adapter-sub", { maxRetriesPerRequest: null });
export const redisSub = create("domain-sub", { maxRetriesPerRequest: null });

export async function closeRedis(): Promise<void> {
  await Promise.allSettled([redis.quit(), redisPub.quit(), redisAdapterSub.quit(), redisSub.quit()]);
}
