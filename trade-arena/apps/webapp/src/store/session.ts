import { create } from "zustand";
import type {
  AccountView,
  ClosedTradeView,
  FeedEvent,
  LeaderboardPayload,
  PositionView,
  SessionSnapshot,
  TournamentResult,
  TournamentView,
  UserProfile,
} from "@arena/shared";

interface SessionState {
  ready: boolean;
  user: UserProfile | null;
  tournament: TournamentView | null;
  account: AccountView | null;
  position: PositionView | null;
  leaderboard: LeaderboardPayload | null;
  recentTrades: ClosedTradeView[];
  feed: FeedEvent[];
  lastResult: TournamentResult | null;
  online: number;
  serverOffsetMs: number;
  hydrate: (s: SessionSnapshot) => void;
  setTournament: (t: TournamentView) => void;
  setAccount: (a: AccountView | null) => void;
  setPosition: (p: PositionView | null) => void;
  setLeaderboard: (l: LeaderboardPayload) => void;
  setTickets: (n: number) => void;
  pushClosed: (t: ClosedTradeView) => void;
  pushFeed: (e: FeedEvent) => void;
  setResult: (r: TournamentResult | null) => void;
  setOnline: (n: number) => void;
}

export const useSession = create<SessionState>()((set) => ({
  ready: false,
  user: null,
  tournament: null,
  account: null,
  position: null,
  leaderboard: null,
  recentTrades: [],
  feed: [],
  lastResult: null,
  online: 0,
  serverOffsetMs: 0,
  hydrate: (s) =>
    set({
      ready: true,
      user: s.user,
      tournament: s.tournament,
      account: s.account,
      position: s.position,
      leaderboard: s.leaderboard,
      serverOffsetMs: s.serverTime - Date.now(),
    }),
  setTournament: (tournament) =>
    set((s) => {
      // A new round resets per-round state.
      if (s.tournament && s.tournament.id !== tournament.id) {
        return { tournament, account: null, position: null, leaderboard: null, recentTrades: [] };
      }
      return { tournament };
    }),
  setAccount: (account) => set({ account }),
  setPosition: (position) => set({ position }),
  setLeaderboard: (leaderboard) =>
    set((s) => {
      if (s.tournament && leaderboard.tournamentId !== s.tournament.id) return {};
      const mine = s.user ? leaderboard.entries.find((e) => e.userId === s.user!.id) : undefined;
      return {
        leaderboard,
        account: s.account && mine ? { ...s.account, rank: mine.rank } : s.account,
      };
    }),
  setTickets: (tickets) => set((s) => (s.user ? { user: { ...s.user, tickets } } : {})),
  pushClosed: (t) => set((s) => ({ recentTrades: [t, ...s.recentTrades.filter((x) => x.tradeId !== t.tradeId)].slice(0, 20) })),
  pushFeed: (e) => set((s) => ({ feed: [e, ...s.feed].slice(0, 30) })),
  setResult: (lastResult) => set({ lastResult }),
  setOnline: (online) => set({ online }),
}));
