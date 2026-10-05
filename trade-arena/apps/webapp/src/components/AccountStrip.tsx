"use client";
import { useSession } from "@/store/session";
import { useLiveEquity } from "@/hooks/useDerived";
import { fmtPct, fmtUsd, pnlColor } from "@/lib/format";

export function AccountStrip() {
  const account = useSession((s) => s.account);
  const live = useLiveEquity();
  if (!account || !live) return null;
  return (
    <div className="mx-4 grid grid-cols-3 gap-2 rounded-xl border border-line bg-panel p-3 text-center">
      <div>
        <div className="text-[10px] uppercase tracking-wide text-muted">Equity</div>
        <div className="tabular text-sm font-semibold">{fmtUsd(live.equity)}</div>
      </div>
      <div>
        <div className="text-[10px] uppercase tracking-wide text-muted">Round PnL</div>
        <div className={`tabular text-sm font-semibold ${pnlColor(live.pnlPct)}`}>{fmtPct(live.pnlPct)}</div>
      </div>
      <div>
        <div className="text-[10px] uppercase tracking-wide text-muted">Rank</div>
        <div className="tabular text-sm font-semibold text-gold">{account.rank ? `#${account.rank}` : "—"}</div>
      </div>
    </div>
  );
}
