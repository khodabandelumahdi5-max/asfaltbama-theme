import { create } from "zustand";
import type { Candle, TickPayload } from "@arena/shared";

export type ConnectionState = "connecting" | "online" | "reconnecting" | "offline" | "unauthorized";

interface MarketState {
  price: number;
  prevPrice: number;
  /** Seed history for the chart; live updates are streamed via `lastTick`. */
  candles: Candle[];
  lastTick: TickPayload | null;
  connection: ConnectionState;
  setHistory: (candles: Candle[], price: number) => void;
  applyTick: (tick: TickPayload) => void;
  setConnection: (c: ConnectionState) => void;
}

export const useMarket = create<MarketState>()((set) => ({
  price: 0,
  prevPrice: 0,
  candles: [],
  lastTick: null,
  connection: "connecting",
  setHistory: (candles, price) => set({ candles, price, prevPrice: price }),
  applyTick: (tick) => set((s) => ({ lastTick: tick, prevPrice: s.price || tick.price, price: tick.price })),
  setConnection: (connection) => set({ connection }),
}));
