import type { Candle, TickPayload } from "@arena/shared";
import { config } from "../config";
import { logger } from "../logger";
import { redis, redisSub } from "../redis/client";
import { keys } from "../redis/keys";

type TickListener = (tick: TickPayload) => void;

/**
 * Per-instance read model of the market. Every server instance keeps this warm by
 * subscribing to the tick channel, so trade execution never needs a Redis round-trip
 * to know the mark price.
 */
class MarketState {
  private lastTick: TickPayload | null = null;
  private candles: Candle[] = [];
  private listeners = new Set<TickListener>();

  get price(): number {
    return this.lastTick?.price ?? config.PRICE_SEED;
  }

  get lastTickAt(): number {
    return this.lastTick?.ts ?? 0;
  }

  get tick(): TickPayload | null {
    return this.lastTick;
  }

  /** Finalised candles plus the in-progress candle. */
  history(limit = config.CANDLE_HISTORY): Candle[] {
    const out = this.candles.slice(-limit);
    const live = this.lastTick?.candle;
    if (live && (out.length === 0 || out[out.length - 1]!.time < live.time)) out.push(live);
    return out.slice(-limit);
  }

  isFresh(maxAgeMs = Math.max(2_000, config.PRICE_TICK_MS * 8)): boolean {
    return this.lastTick !== null && Date.now() - this.lastTick.ts <= maxAgeMs;
  }

  onTick(listener: TickListener): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  apply(tick: TickPayload): void {
    const prev = this.lastTick?.candle;
    if (prev && prev.time < tick.candle.time) {
      this.candles.push(prev);
      if (this.candles.length > config.CANDLE_HISTORY) this.candles.splice(0, this.candles.length - config.CANDLE_HISTORY);
    }
    this.lastTick = tick;
    for (const l of this.listeners) {
      try {
        l(tick);
      } catch (err) {
        logger.error({ err }, "tick listener failed");
      }
    }
  }

  async hydrate(): Promise<void> {
    const [last, rawCandles] = await Promise.all([
      redis.get(keys.priceLast),
      redis.lrange(keys.priceCandles, -config.CANDLE_HISTORY, -1),
    ]);
    this.candles = rawCandles.map((c) => JSON.parse(c) as Candle);
    if (last) this.lastTick = JSON.parse(last) as TickPayload;
  }

  async subscribe(): Promise<void> {
    await redisSub.subscribe(keys.priceChannel);
    redisSub.on("message", (channel, message) => {
      if (channel !== keys.priceChannel) return;
      try {
        const tick = JSON.parse(message) as TickPayload;
        if (!this.lastTick || tick.ts > this.lastTick.ts) this.apply(tick);
      } catch (err) {
        logger.warn({ err }, "bad tick payload");
      }
    });
  }
}

export const market = new MarketState();
