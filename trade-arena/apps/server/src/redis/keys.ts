import { SYMBOL } from "@arena/shared";

/** Centralised Redis key layout. Tournament keys use a hash tag `{t:<id>}` so they co-locate on one Redis Cluster slot (Lua safety). */
export const keys = {
  leaderLock: "arena:leader",
  activeTournament: "arena:tournament:active",
  userNames: "arena:users:names",
  online: "arena:online",
  priceLast: `px:${SYMBOL}:last`,
  priceCandles: `px:${SYMBOL}:candles`,
  priceChannel: `px:${SYMBOL}:ticks`,
  feed: "arena:feed",

  tMeta: (tid: string) => `{t:${tid}}:meta`,
  tAccount: (tid: string, uid: string) => `{t:${tid}}:acct:${uid}`,
  tPosition: (tid: string, uid: string) => `{t:${tid}}:pos:${uid}`,
  tLeaderboard: (tid: string) => `{t:${tid}}:lb`,
  tLeaderboardDirty: (tid: string) => `{t:${tid}}:lb:dirty`,
  tLiqLong: (tid: string) => `{t:${tid}}:liq:long`,
  tLiqShort: (tid: string) => `{t:${tid}}:liq:short`,
  tPlayers: (tid: string) => `{t:${tid}}:players`,
} as const;
