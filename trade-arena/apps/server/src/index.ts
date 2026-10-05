import { config } from "./config";
import { logger } from "./logger";
import { closeDb } from "./db/client";
import { runMigrations } from "./db/migrate";
import { closeRedis } from "./redis/client";
import { market } from "./market/state";
import { PriceSimulator } from "./market/simulator";
import { LeaderElector } from "./cluster/leader";
import { LeaderboardBroadcaster } from "./leaderboard/leaderboard";
import { TournamentScheduler } from "./tournament/manager";
import { getActiveTournamentId } from "./tournament/store";
import { sweepLiquidations } from "./trading/engine";
import { expireDeposits } from "./services/payments";
import { broadcastVolatile } from "./realtime/bus";
import { broadcastOnline, createRealtime } from "./realtime/socket";
import { buildApp, type Health } from "./http/app";

async function main(): Promise<void> {
  runMigrations();
  await market.hydrate();
  await market.subscribe();

  const health: Health = { ready: false, draining: false, isLeader: () => elector.isLeader };
  const app = await buildApp(health);
  const realtime = createRealtime(app.server);

  // ---- Leader-only singleton workloads ----
  const simulator = new PriceSimulator();
  const leaderboard = new LeaderboardBroadcaster(getActiveTournamentId);
  const scheduler = new TournamentScheduler(async (tid, price) => {
    await sweepLiquidations(tid, price);
  });
  let housekeeping: NodeJS.Timeout | null = null;
  let onlineTimer: NodeJS.Timeout | null = null;
  let unsubscribeTicks: (() => void) | null = null;

  const elector = new LeaderElector(
    () => {
      unsubscribeTicks = market.onTick((tick) => broadcastVolatile("market:tick", tick));
      simulator.start();
      leaderboard.start();
      scheduler.start();
      housekeeping = setInterval(() => {
        try {
          const n = expireDeposits();
          if (n > 0) logger.info({ expired: n }, "expired stale deposits");
        } catch (err) {
          logger.error({ err }, "deposit expiry failed");
        }
      }, 60_000);
      onlineTimer = setInterval(() => void broadcastOnline().catch(() => undefined), 5_000);
    },
    () => {
      simulator.stop();
      leaderboard.stop();
      scheduler.stop();
      unsubscribeTicks?.();
      unsubscribeTicks = null;
      if (housekeeping) clearInterval(housekeeping);
      if (onlineTimer) clearInterval(onlineTimer);
      housekeeping = null;
      onlineTimer = null;
    },
  );

  await app.listen({ host: config.SERVER_HOST, port: config.SERVER_PORT });
  elector.start();
  health.ready = true;
  logger.info({ port: config.SERVER_PORT, instance: config.INSTANCE_ID }, "arena server ready");

  // ---- Graceful, zero-downtime shutdown ----
  // 1) fail readiness so the load balancer stops routing, 2) hand leadership to a peer,
  // 3) disconnect local sockets (clients reconnect to peers), 4) drain HTTP, 5) close stores.
  let shuttingDown = false;
  const shutdown = async (signal: string) => {
    if (shuttingDown) return;
    shuttingDown = true;
    logger.info({ signal }, "draining");
    health.draining = true;
    const drainDelayMs = config.NODE_ENV === "production" ? 5_000 : 0;
    if (drainDelayMs) await new Promise((r) => setTimeout(r, drainDelayMs));
    const force = setTimeout(() => {
      logger.error("forced exit after timeout");
      process.exit(1);
    }, 20_000);
    force.unref();
    try {
      await elector.stop();
      await realtime.stop();
      await app.close();
      await closeRedis();
      closeDb();
      logger.info("shutdown complete");
      process.exit(0);
    } catch (err) {
      logger.error({ err }, "shutdown error");
      process.exit(1);
    }
  };
  process.on("SIGTERM", () => void shutdown("SIGTERM"));
  process.on("SIGINT", () => void shutdown("SIGINT"));
  process.on("unhandledRejection", (err) => logger.error({ err }, "unhandled rejection"));
}

main().catch((err) => {
  logger.fatal({ err }, "failed to start");
  process.exit(1);
});
