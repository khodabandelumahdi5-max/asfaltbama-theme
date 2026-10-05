/**
 * Spawns N simulated traders (dev auth) that join the round and trade randomly.
 * Populates the leaderboard/overlay for demos and doubles as a socket load test.
 *
 *   ALLOW_DEV_AUTH=true pnpm --filter @arena/server sim:traders -- --count 25
 */
import { createHmac, randomBytes } from "node:crypto";
import { parseArgs } from "node:util";
import { io, type Socket } from "socket.io-client";
import type { Ack, ClientToServerEvents, ServerToClientEvents, SessionSnapshot } from "@arena/shared";

const { values } = parseArgs({
  options: {
    count: { type: "string", default: "20" },
    url: { type: "string", default: `http://127.0.0.1:${process.env.SERVER_PORT ?? 4000}` },
  },
});

const NAMES = ["satoshi", "whale", "degen", "moonboy", "scalper", "hodler", "bear", "bull", "ape", "quant", "sniper", "chad", "wagmi", "ngmi", "fomo", "rekt", "pump", "laser", "turbo", "alpha"];
type S = Socket<ServerToClientEvents, ClientToServerEvents>;

const call = <T>(s: S, ev: keyof ClientToServerEvents, ...args: unknown[]) =>
  new Promise<Ack<T>>((resolve) => (s.timeout(5_000).emit as (...a: unknown[]) => void)(ev, ...args, (err: Error | null, res: Ack<T>) =>
    resolve(err ? { ok: false, error: "timeout", code: "TIMEOUT" } : res)));

/** Buys tickets through the real deposit flow: create intent → signed (mock) chain webhook. */
async function topUp(devUser: { id: string; username: string }): Promise<void> {
  const secret = process.env.DEPOSIT_WEBHOOK_SECRET;
  if (!secret) return;
  const intent = (await fetch(`${values.url}/api/payments/deposits`, {
    method: "POST",
    headers: { authorization: `dev ${JSON.stringify(devUser)}`, "content-type": "application/json" },
    body: JSON.stringify({ network: Math.random() < 0.5 ? "TRC20" : "TON", tickets: 5 }),
  }).then((r) => r.json())) as { reference: string; network: string; amount: string };
  const body = JSON.stringify({ network: intent.network, amount: intent.amount, txHash: randomBytes(32).toString("hex"), memo: intent.reference });
  const ts = String(Math.floor(Date.now() / 1000));
  await fetch(`${values.url}/api/webhooks/deposits`, {
    method: "POST",
    headers: { "content-type": "application/json", "x-timestamp": ts, "x-signature": createHmac("sha256", secret).update(`${ts}.${body}`).digest("hex") },
    body,
  });
}

function trader(i: number): void {
  const suffix = `sim${String(i).padStart(4, "0")}`;
  const devUser = { id: `dev_${suffix}`, username: `${NAMES[i % NAMES.length]}_${i}` };
  const s: S = io(values.url!, { auth: { devUser }, transports: ["websocket"] });
  let hasPosition = false;
  s.on("position:update", (p) => (hasPosition = p !== null));
  s.on("connect_error", (e) => console.error(`[${devUser.username}] ${e.message}`));
  s.on("connect", async () => {
    const snap = await call<SessionSnapshot>(s, "session:sync");
    if (snap.ok) hasPosition = snap.data.position !== null;
  });

  const act = async () => {
    const snap = await call<SessionSnapshot>(s, "session:sync");
    if (snap.ok && snap.data.tournament && !snap.data.account) {
      const joined = await call(s, "tournament:join");
      if (!joined.ok && joined.code === "INSUFFICIENT_TICKETS") await topUp(devUser).catch(() => undefined);
    }
    if (snap.ok && snap.data.tournament?.status === "ACTIVE") {
      if (hasPosition && Math.random() < 0.45) await call(s, "trade:close");
      else if (!hasPosition && Math.random() < 0.7) {
        const leverage = [5, 10, 20, 25, 50, 75, 100][Math.floor(Math.random() * 7)]!;
        await call(s, "trade:open", { side: Math.random() < 0.5 ? "LONG" : "SHORT", leverage, margin: 200 + Math.round(Math.random() * 2_000) });
      }
    }
    setTimeout(act, 2_000 + Math.random() * 6_000);
  };
  setTimeout(act, Math.random() * 3_000);
}

const n = Number(values.count);
for (let i = 1; i <= n; i++) trader(i);
console.log(`${n} simulated traders running against ${values.url} (Ctrl+C to stop)`);
