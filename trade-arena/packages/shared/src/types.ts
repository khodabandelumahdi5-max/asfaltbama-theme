export type Side = "LONG" | "SHORT";
export type CloseReason = "MANUAL" | "LIQUIDATION" | "TOURNAMENT_END";
export type TournamentStatus = "SCHEDULED" | "ACTIVE" | "SETTLING" | "FINISHED";
export type DepositNetwork = "TRC20" | "TON";
export type DepositStatus = "PENDING" | "CONFIRMED" | "EXPIRED" | "UNDERPAID";

export interface Candle {
  /** Unix seconds, aligned to the candle interval. */
  time: number;
  open: number;
  high: number;
  low: number;
  close: number;
}

export interface TickPayload {
  symbol: string;
  price: number;
  /** Unix milliseconds. */
  ts: number;
  candle: Candle;
}

export interface UserProfile {
  id: string;
  username: string | null;
  firstName: string;
  photoUrl: string | null;
  referralCode: string;
  tickets: number;
  referralCount: number;
}

export interface AccountView {
  tournamentId: string;
  balance: number;
  startingBalance: number;
  realizedPnlPct: number;
  trades: number;
  wins: number;
  rank: number | null;
}

export interface PositionView {
  tradeId: string;
  side: Side;
  leverage: number;
  margin: number;
  qty: number;
  entryPrice: number;
  liquidationPrice: number;
  openFee: number;
  openedAt: number;
}

export interface ClosedTradeView {
  tradeId: string;
  side: Side;
  leverage: number;
  margin: number;
  entryPrice: number;
  exitPrice: number;
  realizedPnl: number;
  roePct: number;
  reason: CloseReason;
  closedAt: number;
  balance: number;
}

export interface LeaderboardEntry {
  rank: number;
  userId: string;
  name: string;
  pnlPct: number;
}

export interface LeaderboardPayload {
  tournamentId: string;
  entries: LeaderboardEntry[];
  players: number;
  updatedAt: number;
}

export interface TournamentView {
  id: string;
  name: string;
  status: TournamentStatus;
  startsAt: number;
  endsAt: number;
  entryTickets: number;
  startingBalance: number;
  players: number;
  prizePoolTickets: number;
}

export interface TournamentResult {
  tournamentId: string;
  name: string;
  winners: Array<LeaderboardEntry & { prizeTickets: number }>;
  finishedAt: number;
}

export interface DepositView {
  id: string;
  reference: string;
  network: DepositNetwork;
  asset: "USDT";
  address: string;
  /** Exact amount to send, as decimal string (TRC20 amounts carry a unique fractional tag). */
  amount: string;
  /** Comment / memo that must be attached (TON). */
  memo: string | null;
  tickets: number;
  status: DepositStatus;
  expiresAt: number;
  createdAt: number;
}

export interface WalletUpdate {
  tickets: number;
  deposit?: DepositView;
}

export interface FeedEvent {
  id: string;
  kind: "OPEN" | "CLOSE" | "LIQUIDATION" | "WINNER";
  name: string;
  side?: Side;
  leverage?: number;
  pnl?: number;
  roePct?: number;
  ts: number;
}

export interface SessionSnapshot {
  user: UserProfile;
  tournament: TournamentView | null;
  account: AccountView | null;
  position: PositionView | null;
  leaderboard: LeaderboardPayload | null;
  candles: Candle[];
  price: number;
  serverTime: number;
}

export interface SpectatorSnapshot {
  tournament: TournamentView | null;
  leaderboard: LeaderboardPayload | null;
  candles: Candle[];
  price: number;
  online: number;
  serverTime: number;
}

export type Ack<T> = { ok: true; data: T } | { ok: false; error: string; code: string };

export interface OpenPositionRequest {
  side: Side;
  leverage: number;
  margin: number;
}

export interface ClientToServerEvents {
  "session:sync": (cb: (res: Ack<SessionSnapshot>) => void) => void;
  "tournament:join": (cb: (res: Ack<{ account: AccountView; tickets: number }>) => void) => void;
  "trade:open": (req: OpenPositionRequest, cb: (res: Ack<{ position: PositionView; account: AccountView }>) => void) => void;
  "trade:close": (cb: (res: Ack<{ trade: ClosedTradeView; account: AccountView }>) => void) => void;
}

export interface ServerToClientEvents {
  "market:tick": (tick: TickPayload) => void;
  "leaderboard:top": (payload: LeaderboardPayload) => void;
  "tournament:state": (tournament: TournamentView) => void;
  "tournament:result": (result: TournamentResult) => void;
  "account:update": (account: AccountView) => void;
  "position:update": (position: PositionView | null) => void;
  "trade:closed": (trade: ClosedTradeView) => void;
  "wallet:update": (update: WalletUpdate) => void;
  "feed:event": (event: FeedEvent) => void;
  "stats:online": (online: number) => void;
}

export interface SpectatorClientToServerEvents {
  "spectator:sync": (cb: (res: Ack<SpectatorSnapshot>) => void) => void;
}

export type SpectatorServerToClientEvents = Pick<
  ServerToClientEvents,
  "market:tick" | "leaderboard:top" | "tournament:state" | "tournament:result" | "feed:event" | "stats:online"
>;
