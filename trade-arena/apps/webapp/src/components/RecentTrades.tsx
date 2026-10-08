"use client";
import { useSession } from "@/store/session";
import { fmtPct, fmtPrice, fmtSigned, pnlColor } from "@/lib/format";

const REASON: Record<string, string> = { MANUAL: "Closed", LIQUIDATION: "Liquidated", TOURNAMENT_END: "Round end" };

export function RecentTrades() {
  const trades = useSession((s) => s.recentTrades);
  if (trades.length === 0) return null;
  return (
    <div className="mx-4 rounded-2xl border border-line bg-panel">
      <div className="border-b border-line px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted">Your trades</div>
      <ul className="divide-y divide-line">
        {trades.slice(0, 6).map((t) => (
          <li key={t.tradeId} data-testid="recent-trade" className="flex items-center justify-between px-4 py-2 text-sm">
            <div>
              <span className={t.side === "LONG" ? "text-up" : "text-down"}>
                {t.side} {t.leverage}×
              </span>
              <span className="ml-2 text-xs text-muted">
                {fmtPrice(t.entryPrice)} → {fmtPrice(t.exitPrice)} · {REASON[t.reason]}
              </span>
            </div>
            <div className={`tabular text-right font-semibold ${pnlColor(t.realizedPnl)}`}>
              {fmtSigned(t.realizedPnl)}
              <div className="text-[10px] font-normal">{fmtPct(t.roePct)}</div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
