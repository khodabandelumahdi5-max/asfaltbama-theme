import { io, type Socket } from "socket.io-client";
import {
  SPECTATOR_NAMESPACE,
  type Ack,
  type SpectatorClientToServerEvents,
  type SpectatorServerToClientEvents,
  type SpectatorSnapshot,
} from "@arena/shared";
import { SERVER_URL } from "./config";
import { useOverlay } from "./store";

declare global {
  interface Window {
    __OVERLAY_READY__?: boolean;
    __OVERLAY_HEARTBEAT__?: number;
  }
}

const set = useOverlay.setState;
let resultTimer: ReturnType<typeof setTimeout> | null = null;
let singleton: Socket<SpectatorServerToClientEvents, SpectatorClientToServerEvents> | null = null;

/** Idempotent: React StrictMode double-invokes effects in dev, but only one connection is ever opened. */
export function connectSpectator(): Socket<SpectatorServerToClientEvents, SpectatorClientToServerEvents> {
  if (singleton) return singleton;
  const socket: Socket<SpectatorServerToClientEvents, SpectatorClientToServerEvents> = io(`${SERVER_URL}${SPECTATOR_NAMESPACE}`, {
    transports: ["websocket"],
    reconnectionDelay: 500,
    reconnectionDelayMax: 3_000,
  });
  singleton = socket;

  const sync = () =>
    socket.timeout(5_000).emit("spectator:sync", (err: Error | null, res: Ack<SpectatorSnapshot>) => {
      if (err || !res.ok) return;
      const s = res.data;
      set({
        tournament: s.tournament,
        leaderboard: s.leaderboard,
        candles: s.candles,
        price: s.price,
        prevPrice: s.price,
        online: s.online,
        serverOffsetMs: s.serverTime - Date.now(),
      });
      window.__OVERLAY_READY__ = true;
      window.__OVERLAY_HEARTBEAT__ = Date.now();
    });

  socket.on("connect", () => {
    set({ connected: true });
    sync();
  });
  socket.on("disconnect", () => set({ connected: false }));

  socket.on("market:tick", (tick) => {
    set((s) => ({ lastTick: tick, prevPrice: s.price || tick.price, price: tick.price }));
    window.__OVERLAY_HEARTBEAT__ = Date.now();
  });
  socket.on("leaderboard:top", (leaderboard) =>
    set((s) => (s.tournament && leaderboard.tournamentId !== s.tournament.id ? {} : { leaderboard })),
  );
  socket.on("tournament:state", (tournament) =>
    set((s) => (s.tournament && s.tournament.id !== tournament.id ? { tournament, leaderboard: null } : { tournament })),
  );
  socket.on("tournament:result", (result) => {
    set({ result });
    if (resultTimer) clearTimeout(resultTimer);
    resultTimer = setTimeout(() => set({ result: null }), 15_000);
  });
  socket.on("feed:event", (e) => set((s) => ({ feed: [e, ...s.feed].slice(0, 8) })));
  socket.on("stats:online", (online) => set({ online }));

  // Periodic full resync guards against any dropped volatile frames on a 24/7 stream.
  setInterval(() => socket.connected && sync(), 60_000);
  return socket;
}
