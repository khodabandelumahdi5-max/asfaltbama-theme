export const SYMBOL = "BTCUSDT" as const;
export const SYMBOL_LABEL = "BTC/USDT" as const;

export const MAX_LEVERAGE = 100;
export const MIN_LEVERAGE = 1;
export const MIN_MARGIN = 10;
/** Taker fee charged on notional at open and at close. */
export const TAKER_FEE_RATE = 0.0004;
/** Maintenance margin rate used for liquidation price. */
export const MAINTENANCE_MARGIN_RATE = 0.005;
export const LEADERBOARD_SIZE = 10;

export const ROOMS = {
  arena: "arena",
  user: (userId: string) => `user:${userId}`,
} as const;

export const SPECTATOR_NAMESPACE = "/spectator";

export const NOTIFY_CHANNEL = "tg:notify";
