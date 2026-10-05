import { create } from "zustand";
import type { Candle, FeedEvent, LeaderboardPayload, TickPayload, TournamentResult, TournamentView } from "@arena/shared";

interface OverlayState {
  connected: boolean;
  price: number;
  prevPrice: number;
  candles: Candle[];
  lastTick: TickPayload | null;
  tournament: TournamentView | null;
  leaderboard: LeaderboardPayload | null;
  feed: FeedEvent[];
  online: number;
  result: TournamentResult | null;
  serverOffsetMs: number;
}

export const useOverlay = create<OverlayState>()(() => ({
  connected: false,
  price: 0,
  prevPrice: 0,
  candles: [],
  lastTick: null,
  tournament: null,
  leaderboard: null,
  feed: [],
  online: 0,
  result: null,
  serverOffsetMs: 0,
}));
