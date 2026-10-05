import { MAINTENANCE_MARGIN_RATE, TAKER_FEE_RATE } from "./constants";
import type { Side } from "./types";

export function direction(side: Side): 1 | -1 {
  return side === "LONG" ? 1 : -1;
}

/** Isolated-margin liquidation price. */
export function liquidationPrice(side: Side, entryPrice: number, leverage: number, mmr = MAINTENANCE_MARGIN_RATE): number {
  return side === "LONG"
    ? entryPrice * (1 - 1 / leverage + mmr)
    : entryPrice * (1 + 1 / leverage - mmr);
}

/** Gross unrealized PnL (before close fee). */
export function unrealizedPnl(side: Side, entryPrice: number, markPrice: number, qty: number): number {
  return (markPrice - entryPrice) * qty * direction(side);
}

/** Net PnL if the position were closed now at markPrice (including both fees), floored at -(margin + openFee). */
export function netPnlIfClosed(
  side: Side,
  entryPrice: number,
  markPrice: number,
  qty: number,
  margin: number,
  openFee: number,
  feeRate = TAKER_FEE_RATE,
): number {
  const gross = unrealizedPnl(side, entryPrice, markPrice, qty);
  const closeFee = qty * markPrice * feeRate;
  const payout = Math.max(0, margin + gross - closeFee);
  return payout - margin - openFee;
}

export function roePct(pnl: number, margin: number): number {
  return margin > 0 ? (pnl / margin) * 100 : 0;
}

export function round(value: number, decimals = 2): number {
  const f = 10 ** decimals;
  return Math.round(value * f) / f;
}
