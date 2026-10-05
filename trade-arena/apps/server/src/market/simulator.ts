import { SYMBOL, type Candle, type TickPayload } from "@arena/shared";
import { config } from "../config";
import { logger } from "../logger";
import { redisPub } from "../redis/client";
import { keys } from "../redis/keys";
import { market } from "./state";

const MS_PER_YEAR = 365 * 24 * 3600 * 1000;

/** Standard normal via Box–Muller. */
function gaussian(): number {
  let u = 0;
  let v = 0;
  while (u === 0) u = Math.random();
  while (v === 0) v = Math.random();
  return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * v);
}

/**
 * BTC/USDT price process: geometric Brownian motion with
 *  - stochastic volatility (mean-reverting OU on a vol multiplier → calm/volatile regimes),
 *  - momentum (OU drift → tradable micro-trends),
 *  - rare Poisson jumps (wicks / squeezes),
 *  - weak log mean-reversion to the seed so a 24/7 stream never drifts to absurd levels.
 * Runs only on the elected leader instance.
 */
export class PriceSimulator {
  private timer: NodeJS.Timeout | null = null;
  private price: number;
  private candle: Candle | null;
  private volMult = 1;
  private momentum = 0;
  private nextAt = 0;
  private running = false;

  constructor() {
    const last = market.tick;
    this.price = last?.price ?? config.PRICE_SEED;
    this.candle = last?.candle ?? null;
  }

  start(): void {
    if (this.running) return;
    this.running = true;
    const last = market.tick;
    if (last) {
      this.price = last.price;
      this.candle = last.candle;
    }
    this.nextAt = Date.now();
    logger.info({ price: this.price }, "price simulator started");
    this.loop();
  }

  stop(): void {
    this.running = false;
    if (this.timer) clearTimeout(this.timer);
    this.timer = null;
    logger.info("price simulator stopped");
  }

  private loop = (): void => {
    if (!this.running) return;
    try {
      this.step();
    } catch (err) {
      logger.error({ err }, "price step failed");
    }
    // Drift-compensated scheduling keeps a steady tick cadence under event-loop jitter.
    this.nextAt += config.PRICE_TICK_MS;
    const delay = Math.max(0, this.nextAt - Date.now());
    if (delay === 0 && Date.now() - this.nextAt > config.PRICE_TICK_MS * 10) this.nextAt = Date.now();
    this.timer = setTimeout(this.loop, delay);
  };

  private step(): void {
    const now = Date.now();
    const dt = config.PRICE_TICK_MS / MS_PER_YEAR;

    // Stochastic vol multiplier: OU around 1, clamped [0.4, 4].
    this.volMult += 0.02 * (1 - this.volMult) + 0.06 * gaussian();
    this.volMult = Math.min(4, Math.max(0.4, this.volMult));

    // Momentum: OU around 0 in annualised drift units.
    this.momentum += -0.01 * this.momentum + 0.35 * gaussian();
    this.momentum = Math.max(-25, Math.min(25, this.momentum));

    const sigma = config.PRICE_ANNUAL_VOL * this.volMult;
    const reversion = -0.5 * Math.log(this.price / config.PRICE_SEED);
    const drift = this.momentum + reversion;

    let logReturn = (drift - 0.5 * sigma * sigma) * dt + sigma * Math.sqrt(dt) * gaussian();
    if (Math.random() < 0.0008) logReturn += gaussian() * 0.0035; // jump
    this.price = Math.max(1, this.price * Math.exp(logReturn));
    const price = Math.round(this.price * 100) / 100;

    const bucket = Math.floor(now / 1000 / config.CANDLE_INTERVAL_SEC) * config.CANDLE_INTERVAL_SEC;
    const pipeline = redisPub.pipeline();
    if (!this.candle || this.candle.time !== bucket) {
      if (this.candle) {
        pipeline.rpush(keys.priceCandles, JSON.stringify(this.candle));
        pipeline.ltrim(keys.priceCandles, -config.CANDLE_HISTORY, -1);
      }
      const open = this.candle?.close ?? price;
      this.candle = { time: bucket, open, high: Math.max(open, price), low: Math.min(open, price), close: price };
    } else {
      this.candle.high = Math.max(this.candle.high, price);
      this.candle.low = Math.min(this.candle.low, price);
      this.candle.close = price;
    }

    const tick: TickPayload = { symbol: SYMBOL, price, ts: now, candle: { ...this.candle } };
    const json = JSON.stringify(tick);
    pipeline.set(keys.priceLast, json);
    pipeline.publish(keys.priceChannel, json);
    pipeline.exec().catch((err) => logger.error({ err }, "tick publish failed"));

    // Apply locally immediately; the pub/sub echo is ignored because its ts is not newer.
    market.apply(tick);
  }
}
