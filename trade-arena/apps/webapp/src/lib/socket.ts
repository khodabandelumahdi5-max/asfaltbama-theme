import { io, type Socket } from "socket.io-client";
import type {
  AccountView,
  Ack,
  ClientToServerEvents,
  ClosedTradeView,
  OpenPositionRequest,
  PositionView,
  ServerToClientEvents,
  SessionSnapshot,
} from "@arena/shared";
import { SERVER_URL } from "./config";
import { authPayload, haptic } from "./telegram";
import { fmtPct, fmtSigned } from "./format";
import { useMarket } from "@/store/market";
import { useSession } from "@/store/session";
import { toast } from "@/store/toast";

type ArenaSocket = Socket<ServerToClientEvents, ClientToServerEvents>;

let socket: ArenaSocket | null = null;

const ACK_TIMEOUT_MS = 6_000;

function emitAck<T>(event: keyof ClientToServerEvents, ...args: unknown[]): Promise<Ack<T>> {
  const s = socket;
  if (!s || !s.connected) return Promise.resolve({ ok: false, error: "Not connected", code: "OFFLINE" });
  return new Promise((resolve) => {
    (s.timeout(ACK_TIMEOUT_MS).emit as (...a: unknown[]) => void)(event, ...args, (err: Error | null, res: Ack<T>) => {
      resolve(err ? { ok: false, error: "Server did not respond", code: "TIMEOUT" } : res);
    });
  });
}

export async function syncSession(): Promise<void> {
  const res = await emitAck<SessionSnapshot>("session:sync");
  if (!res.ok) {
    toast.error(res.error);
    return;
  }
  useSession.getState().hydrate(res.data);
  useMarket.getState().setHistory(res.data.candles, res.data.price);
}

export function connectSocket(): ArenaSocket {
  if (socket) return socket;
  const s: ArenaSocket = io(SERVER_URL, {
    path: "/socket.io",
    transports: ["websocket"],
    auth: (cb) => cb(authPayload()),
    reconnectionDelay: 500,
    reconnectionDelayMax: 4_000,
  });
  socket = s;
  const market = useMarket.getState;
  const session = useSession.getState;

  s.on("connect", () => {
    market().setConnection("online");
    void syncSession();
  });
  s.on("disconnect", (reason) => {
    market().setConnection(reason === "io client disconnect" ? "offline" : "reconnecting");
  });
  s.on("connect_error", (err) => {
    const code = (err as Error & { data?: { code?: string } }).data?.code;
    if (code?.startsWith("AUTH")) {
      market().setConnection("unauthorized");
      s.disconnect();
    } else {
      market().setConnection("reconnecting");
    }
  });

  s.on("market:tick", (tick) => market().applyTick(tick));
  s.on("leaderboard:top", (lb) => session().setLeaderboard(lb));
  s.on("tournament:state", (t) => {
    const prev = session().tournament;
    session().setTournament(t);
    if (prev?.status !== "ACTIVE" && t.status === "ACTIVE") {
      haptic.notify("success");
      toast.info(`${t.name} is LIVE — trade!`);
    }
    if (prev && prev.id !== t.id) void syncSession();
  });
  s.on("tournament:result", (r) => {
    session().setResult(r);
    const me = session().user?.id;
    const mine = r.winners.find((w) => w.userId === me);
    if (mine) haptic.notify("success");
  });
  s.on("account:update", (a) => session().setAccount(a));
  s.on("position:update", (p) => session().setPosition(p));
  s.on("trade:closed", (t) => {
    session().pushClosed(t);
    if (t.reason === "LIQUIDATION") {
      haptic.notify("error");
      toast.error(`Liquidated ${t.side} ${t.leverage}× · ${fmtSigned(t.realizedPnl)} USDT`);
    } else if (t.reason === "TOURNAMENT_END") {
      toast.info(`Round ended · position closed ${fmtSigned(t.realizedPnl)} USDT`);
    }
  });
  s.on("wallet:update", (w) => {
    session().setTickets(w.tickets);
    if (w.deposit?.status === "CONFIRMED") {
      haptic.notify("success");
      toast.success(`Deposit confirmed · +${w.deposit.tickets} 🎟`);
    } else if (w.deposit?.status === "UNDERPAID") {
      toast.error(`Deposit ${w.deposit.reference} underpaid — contact support`);
    }
  });
  s.on("feed:event", (e) => session().pushFeed(e));
  s.on("stats:online", (n) => session().setOnline(n));

  return s;
}

export async function joinTournament(): Promise<boolean> {
  const res = await emitAck<{ account: AccountView; tickets: number }>("tournament:join");
  if (!res.ok) {
    haptic.notify("error");
    toast.error(res.error);
    return false;
  }
  useSession.getState().setAccount(res.data.account);
  useSession.getState().setTickets(res.data.tickets);
  haptic.notify("success");
  toast.success("You're in! Good luck 🍀");
  return true;
}

export async function openTrade(req: OpenPositionRequest): Promise<boolean> {
  haptic.impact("medium");
  const res = await emitAck<{ position: PositionView; account: AccountView }>("trade:open", req);
  if (!res.ok) {
    haptic.notify("error");
    toast.error(res.error);
    return false;
  }
  useSession.getState().setPosition(res.data.position);
  useSession.getState().setAccount(res.data.account);
  haptic.notify("success");
  return true;
}

export async function closeTrade(): Promise<boolean> {
  haptic.impact("heavy");
  const res = await emitAck<{ trade: ClosedTradeView; account: AccountView }>("trade:close");
  if (!res.ok) {
    haptic.notify("error");
    toast.error(res.error);
    return false;
  }
  const { trade, account } = res.data;
  useSession.getState().setPosition(null);
  useSession.getState().setAccount(account);
  useSession.getState().pushClosed(trade);
  haptic.notify(trade.realizedPnl >= 0 ? "success" : "warning");
  toast[trade.realizedPnl >= 0 ? "success" : "info"](`Closed ${fmtSigned(trade.realizedPnl)} USDT (${fmtPct(trade.roePct)})`);
  return true;
}
