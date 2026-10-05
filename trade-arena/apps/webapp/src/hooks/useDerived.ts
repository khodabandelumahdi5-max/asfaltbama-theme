"use client";
import { netPnlIfClosed, roePct } from "@arena/shared";
import { useMarket } from "@/store/market";
import { useSession } from "@/store/session";

/** Live unrealized PnL of the open position, recomputed on every tick. */
export function useLivePosition() {
  const position = useSession((s) => s.position);
  const price = useMarket((s) => s.price);
  if (!position || !price) return null;
  const pnl = netPnlIfClosed(position.side, position.entryPrice, price, position.qty, position.margin, position.openFee);
  const span = Math.abs(position.entryPrice - position.liquidationPrice);
  const distance = Math.abs(price - position.liquidationPrice);
  return {
    position,
    mark: price,
    pnl,
    roe: roePct(pnl, position.margin),
    liqProximity: span > 0 ? Math.max(0, Math.min(1, 1 - distance / span)) : 0,
  };
}

/** Live equity = balance + margin locked + unrealized PnL; used for the header PnL%. */
export function useLiveEquity() {
  const account = useSession((s) => s.account);
  const live = useLivePosition();
  if (!account) return null;
  const equity = account.balance + (live ? live.position.margin + live.position.openFee + live.pnl : 0);
  return { equity, pnlPct: ((equity - account.startingBalance) / account.startingBalance) * 100 };
}
