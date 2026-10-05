import { LEADERBOARD_SIZE, round, type LeaderboardEntry, type LeaderboardPayload } from "@arena/shared";
import { config } from "../config";
import { logger } from "../logger";
import { redis } from "../redis/client";
import { keys } from "../redis/keys";
import { broadcast } from "../realtime/bus";

/** Records a realized PnL% for a user. Lua scripts call ZADD directly; this is for out-of-band corrections. */
export async function setScore(tournamentId: string, userId: string, pnlPct: number): Promise<void> {
  await redis
    .multi()
    .zadd(keys.tLeaderboard(tournamentId), pnlPct, userId)
    .set(keys.tLeaderboardDirty(tournamentId), "1")
    .exec();
}

/** Top-N via ZREVRANGE ... WITHSCORES + one HMGET for names: two round-trips (auto-pipelined), sub-millisecond on Redis. */
export async function getTop(tournamentId: string, limit = LEADERBOARD_SIZE): Promise<LeaderboardPayload> {
  const [raw, players] = await Promise.all([
    redis.zrevrange(keys.tLeaderboard(tournamentId), 0, limit - 1, "WITHSCORES"),
    redis.zcard(keys.tLeaderboard(tournamentId)),
  ]);
  const ids: string[] = [];
  const scores: number[] = [];
  for (let i = 0; i < raw.length; i += 2) {
    ids.push(raw[i]!);
    scores.push(Number(raw[i + 1]));
  }
  const names = ids.length ? await redis.hmget(keys.userNames, ...ids) : [];
  const entries: LeaderboardEntry[] = ids.map((userId, i) => ({
    rank: i + 1,
    userId,
    name: names[i] ?? "Trader",
    pnlPct: round(scores[i]!, 4),
  }));
  return { tournamentId, entries, players, updatedAt: Date.now() };
}

export async function getRank(tournamentId: string, userId: string): Promise<number | null> {
  const r = await redis.zrevrank(keys.tLeaderboard(tournamentId), userId);
  return r === null ? null : r + 1;
}

/**
 * Leader-only coalescing broadcaster. Every PnL commit sets a dirty flag atomically in Lua;
 * this loop GETDELs it and broadcasts the fresh Top 10 at most once per LEADERBOARD_FLUSH_MS,
 * so bursts of thousands of trades produce a bounded number of socket frames.
 */
export class LeaderboardBroadcaster {
  private timer: NodeJS.Timeout | null = null;

  constructor(private readonly activeTournamentId: () => Promise<string | null>) {}

  start(): void {
    if (this.timer) return;
    this.timer = setInterval(() => void this.flush(), config.LEADERBOARD_FLUSH_MS);
  }

  stop(): void {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
  }

  async flush(force = false): Promise<void> {
    try {
      const tid = await this.activeTournamentId();
      if (!tid) return;
      const dirty = await redis.getdel(keys.tLeaderboardDirty(tid));
      if (!dirty && !force) return;
      broadcast("leaderboard:top", await getTop(tid));
    } catch (err) {
      logger.error({ err }, "leaderboard flush failed");
    }
  }
}
