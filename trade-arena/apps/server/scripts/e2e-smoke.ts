/**
 * End-to-end smoke test against a running server: Telegram-signed auth, join, open/close,
 * leaderboard broadcast, liquidation index, deposit intent + signed webhook credit.
 *
 *   pnpm --filter @arena/server test:e2e
 */
import { createHmac, randomBytes } from "node:crypto";
import { io, type Socket } from "socket.io-client";
import type { Ack, ClientToServerEvents, LeaderboardPayload, ServerToClientEvents } from "@arena/shared";

const URL = process.env.SMOKE_URL ?? `http://127.0.0.1:${process.env.SERVER_PORT ?? 4000}`;
const token = process.env.TELEGRAM_BOT_TOKEN!;
const webhookSecret = process.env.DEPOSIT_WEBHOOK_SECRET!;

function signInitData(user: { id: number; first_name: string; username: string }): string {
  const params = new URLSearchParams({
    auth_date: String(Math.floor(Date.now() / 1000)),
    query_id: "AAH" + randomBytes(6).toString("hex"),
    user: JSON.stringify(user),
  });
  const dcs = [...params.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([k, v]) => `${k}=${v}`).join("\n");
  const secret = createHmac("sha256", "WebAppData").update(token).digest();
  params.set("hash", createHmac("sha256", secret).update(dcs).digest("hex"));
  return params.toString();
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const json = async (r: Response | Promise<Response>): Promise<any> => (await r).json();

function assert(cond: unknown, msg: string): asserts cond {
  if (!cond) throw new Error(`ASSERTION FAILED: ${msg}`);
  console.log(`  ✓ ${msg}`);
}

type S = Socket<ServerToClientEvents, ClientToServerEvents>;
function call<T>(s: S, ev: string, ...args: unknown[]): Promise<Ack<T>> {
  return new Promise((resolve) => (s.emit as (...a: unknown[]) => void)(ev, ...args, resolve));
}

async function main() {
  const uid = 900_000_000 + Math.floor(Math.random() * 1_000_000);
  const initData = signInitData({ id: uid, first_name: "Smoke", username: `smoke${uid}` });

  console.log("auth");
  const bad = io(URL, { auth: { initData: initData.replace(/hash=[a-f0-9]+/, "hash=" + "0".repeat(64)) }, transports: ["websocket"] });
  const badErr = await new Promise<Error>((r) => bad.on("connect_error", r));
  assert(/signature/.test(badErr.message), "tampered initData rejected");
  bad.close();

  const s: S = io(URL, { auth: { initData }, transports: ["websocket"] });
  await new Promise<void>((r, j) => { s.on("connect", r); s.on("connect_error", j); });
  assert(true, "valid initData accepted");

  const boards: LeaderboardPayload[] = [];
  s.on("leaderboard:top", (p) => boards.push(p));
  let ticks = 0;
  s.on("market:tick", () => ticks++);

  const snap = await call<{ user: { tickets: number }; tournament: { status: string } | null; candles: unknown[] }>(s, "session:sync");
  assert(snap.ok && snap.data.user.tickets >= 1, "session snapshot with welcome tickets");
  assert(snap.ok && snap.data.tournament, "tournament present");

  const notJoined = await call(s, "trade:open", { side: "LONG", leverage: 10, margin: 100 });
  assert(!notJoined.ok, "cannot trade before joining");

  const join = await call<{ tickets: number }>(s, "tournament:join");
  assert(join.ok, "joined tournament");
  const join2 = await call<{ tickets: number }>(s, "tournament:join");
  assert(join2.ok && join.ok && join2.data.tickets === join.data.tickets, "re-join is idempotent (no double charge)");

  // wait until ACTIVE
  for (let i = 0; i < 40; i++) {
    const r = await call<{ tournament: { status: string } }>(s, "session:sync");
    if (r.ok && r.data.tournament.status === "ACTIVE") break;
    await new Promise((x) => setTimeout(x, 500));
  }

  const open = await call<{ position: { entryPrice: number; liquidationPrice: number; qty: number } }>(s, "trade:open", { side: "LONG", leverage: 50, margin: 1000 });
  assert(open.ok, `opened LONG 50x ${open.ok ? `@ ${open.data.position.entryPrice} liq ${open.data.position.liquidationPrice.toFixed(2)}` : JSON.stringify(open)}`);
  const dup = await call(s, "trade:open", { side: "SHORT", leverage: 5, margin: 100 });
  assert(!dup.ok && dup.code === "POSITION_EXISTS", "second position rejected");
  const invalid = await call(s, "trade:open", { side: "LONG", leverage: 500, margin: 100 });
  assert(!invalid.ok && invalid.code === "INVALID_ORDER", "leverage > 100 rejected");

  await new Promise((r) => setTimeout(r, 1500));
  const close = await call<{ trade: { realizedPnl: number; reason: string }; account: { balance: number; rank: number } }>(s, "trade:close");
  assert(close.ok, `closed: pnl ${close.ok ? close.data.trade.realizedPnl.toFixed(2) : ""} rank ${close.ok ? close.data.account.rank : ""}`);
  await new Promise((r) => setTimeout(r, 600));
  assert(boards.some((b) => b.entries.some((e) => e.userId === String(uid))), "leaderboard:top broadcast contains user");
  assert(ticks > 5, `received ${ticks} market ticks`);

  console.log("REST + payments");
  const auth = { authorization: `tma ${initData}`, "content-type": "application/json" };
  const t0 = performance.now();
  const lb = await json(fetch(`${URL}/api/tournaments/current`));
  console.log(`  leaderboard REST in ${(performance.now() - t0).toFixed(1)}ms, players=${lb.leaderboard?.players}`);
  const dep = await json(fetch(`${URL}/api/payments/deposits`, { method: "POST", headers: auth, body: JSON.stringify({ network: "TRC20", tickets: 5 }) }));
  assert(/^TA-[0-9A-Z]{4}-[0-9A-Z]{4}$/.test(dep.reference) && dep.amount.startsWith("5."), `deposit intent ${dep.reference} amount ${dep.amount}`);

  let walletTickets = -1;
  s.on("wallet:update", (w) => (walletTickets = w.tickets));
  const send = async (body: object, secret = webhookSecret) => {
    const raw = JSON.stringify(body);
    const ts = String(Math.floor(Date.now() / 1000));
    const sig = createHmac("sha256", secret).update(`${ts}.${raw}`).digest("hex");
    return fetch(`${URL}/api/webhooks/deposits`, { method: "POST", headers: { "content-type": "application/json", "x-timestamp": ts, "x-signature": sig }, body: raw });
  };
  const tx = randomBytes(32).toString("hex");
  assert((await send({ network: "TRC20", txHash: tx, amount: dep.amount }, "wrong-secret-wrong-secret")).status === 401, "bad webhook signature rejected");
  const credited = await send({ network: "TRC20", txHash: tx, amount: dep.amount });
  const cj = await json(credited);
  assert(credited.status === 200 && cj.status === "credited", "TRC20 deposit matched by tagged amount and credited");
  const again = await json(send({ network: "TRC20", txHash: tx, amount: dep.amount }));
  assert(again.status === "duplicate", "webhook replay is idempotent");
  await new Promise((r) => setTimeout(r, 300));
  assert(walletTickets === cj.tickets, `wallet:update pushed (tickets=${walletTickets})`);

  s.close();
  console.log("ALL GOOD");
  process.exit(0);
}
main().catch((e) => { console.error(e); process.exit(1); });
