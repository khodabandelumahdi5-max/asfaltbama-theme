import { config } from "../config";
import { logger } from "../logger";
import { redis } from "../redis/client";
import { keys } from "../redis/keys";

const TTL_MS = 6_000;
const RENEW_EVERY_MS = 2_000;

type Hook = () => void | Promise<void>;

/**
 * Redis-lease leader election. Exactly one instance runs the singleton workloads
 * (price feed, liquidation sweeps, leaderboard flush, tournament scheduler). If the
 * leader dies or is drained, another instance acquires the lease within TTL_MS,
 * enabling rolling deploys with zero downtime.
 */
export class LeaderElector {
  private timer: NodeJS.Timeout | null = null;
  private leader = false;
  private stopped = false;

  constructor(
    private readonly onElected: Hook,
    private readonly onDemoted: Hook,
  ) {}

  get isLeader(): boolean {
    return this.leader;
  }

  start(): void {
    this.stopped = false;
    void this.tick();
  }

  private async tick(): Promise<void> {
    if (this.stopped) return;
    try {
      if (this.leader) {
        const renewed = await redis.renewLock(keys.leaderLock, config.INSTANCE_ID, String(TTL_MS));
        if (renewed !== 1) await this.demote("lease lost");
      } else {
        const acquired = await redis.set(keys.leaderLock, config.INSTANCE_ID, "PX", TTL_MS, "NX");
        if (acquired === "OK") {
          this.leader = true;
          logger.info("acquired leadership");
          await this.onElected();
        }
      }
    } catch (err) {
      logger.error({ err }, "leader election tick failed");
      if (this.leader) await this.demote("redis error");
    } finally {
      if (!this.stopped) this.timer = setTimeout(() => void this.tick(), RENEW_EVERY_MS);
    }
  }

  private async demote(reason: string): Promise<void> {
    if (!this.leader) return;
    this.leader = false;
    logger.warn({ reason }, "lost leadership");
    await this.onDemoted();
  }

  /** Releases the lease so a peer takes over immediately (used on graceful shutdown). */
  async stop(): Promise<void> {
    this.stopped = true;
    if (this.timer) clearTimeout(this.timer);
    if (this.leader) {
      await this.demote("shutdown");
      await redis.releaseLock(keys.leaderLock, config.INSTANCE_ID).catch(() => undefined);
    }
  }
}
