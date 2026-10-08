"use client";
import { useState } from "react";
import { useLivePosition } from "@/hooks/useDerived";
import { closeTrade } from "@/lib/socket";
import { fmtPct, fmtPrice, fmtSigned, pnlColor } from "@/lib/format";

export function PositionCard() {
  const live = useLivePosition();
  const [busy, setBusy] = useState(false);
  if (!live) return null;
  const { position: p, pnl, roe, mark, liqProximity } = live;
  const long = p.side === "LONG";

  return (
    <div data-testid="position" className={`mx-4 rounded-2xl border p-4 ${long ? "border-up/40 bg-up/5" : "border-down/40 bg-down/5"}`}>
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className={`rounded-md px-2 py-0.5 text-xs font-bold ${long ? "bg-up text-black" : "bg-down text-white"}`}>
            {p.side} {p.leverage}×
          </span>
          <span className="tabular text-xs text-muted">margin {fmtPrice(p.margin)}</span>
        </div>
        <span className="text-xs text-muted">Unrealized</span>
      </div>

      <div className="mt-2 flex items-end justify-between">
        <div data-testid="position-pnl" className={`tabular text-3xl font-bold ${pnlColor(pnl)}`}>{fmtSigned(pnl)}</div>
        <div className={`tabular text-lg font-semibold ${pnlColor(roe)}`}>{fmtPct(roe)}</div>
      </div>

      <dl className="mt-3 grid grid-cols-3 gap-2 text-xs">
        <div>
          <dt className="text-muted">Entry</dt>
          <dd className="tabular font-medium">{fmtPrice(p.entryPrice)}</dd>
        </div>
        <div>
          <dt className="text-muted">Mark</dt>
          <dd className="tabular font-medium">{fmtPrice(mark)}</dd>
        </div>
        <div>
          <dt className="text-muted">Liq.</dt>
          <dd className="tabular font-medium text-gold">{fmtPrice(p.liquidationPrice)}</dd>
        </div>
      </dl>

      <div className="mt-3">
        <div className="mb-1 flex justify-between text-[10px] uppercase tracking-wide text-muted">
          <span>Liquidation risk</span>
          <span>{Math.round(liqProximity * 100)}%</span>
        </div>
        <div className="h-1.5 overflow-hidden rounded-full bg-line">
          <div
            className={`h-full rounded-full transition-[width] duration-300 ${liqProximity > 0.75 ? "bg-down" : liqProximity > 0.4 ? "bg-gold" : "bg-up"}`}
            style={{ width: `${liqProximity * 100}%` }}
          />
        </div>
      </div>

      <button
        data-testid="close-position"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          await closeTrade();
          setBusy(false);
        }}
        className="mt-4 h-12 w-full rounded-xl bg-text text-base font-bold text-bg active:scale-[0.99] disabled:opacity-50"
      >
        {busy ? "Closing…" : `Close at market · ${fmtSigned(pnl)}`}
      </button>
    </div>
  );
}
