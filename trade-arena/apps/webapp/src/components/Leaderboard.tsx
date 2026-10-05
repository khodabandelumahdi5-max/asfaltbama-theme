"use client";
import { useSession } from "@/store/session";
import { useLiveEquity } from "@/hooks/useDerived";
import { fmtPct, pnlColor } from "@/lib/format";

const MEDALS = ["🥇", "🥈", "🥉"];

export function Leaderboard() {
  const lb = useSession((s) => s.leaderboard);
  const me = useSession((s) => s.user);
  const account = useSession((s) => s.account);
  const feed = useSession((s) => s.feed);
  const live = useLiveEquity();
  const inTop = lb?.entries.some((e) => e.userId === me?.id);

  return (
    <div className="space-y-3 p-4">
      <div className="flex items-baseline justify-between">
        <h2 className="text-lg font-bold">Top 10 traders</h2>
        <span className="text-xs text-muted">{lb?.players ?? 0} players · realized PnL %</span>
      </div>
      <ol className="overflow-hidden rounded-2xl border border-line bg-panel">
        {(!lb || lb.entries.length === 0) && <li className="px-4 py-8 text-center text-sm text-muted">No ranked traders yet — be the first!</li>}
        {lb?.entries.map((e) => (
          <li
            key={e.userId}
            className={`flex items-center justify-between border-b border-line px-4 py-3 last:border-b-0 ${e.userId === me?.id ? "bg-gold/10" : ""}`}
          >
            <span className="flex min-w-0 items-center gap-3">
              <span className="w-7 text-center text-base font-bold text-muted">{MEDALS[e.rank - 1] ?? e.rank}</span>
              <span className="truncate font-medium">
                {e.name}
                {e.userId === me?.id && <span className="ml-1 text-xs text-gold">(you)</span>}
              </span>
            </span>
            <span className={`tabular font-semibold ${pnlColor(e.pnlPct)}`}>{fmtPct(e.pnlPct)}</span>
          </li>
        ))}
      </ol>

      {account && !inTop && (
        <div className="flex items-center justify-between rounded-2xl border border-gold/40 bg-gold/10 px-4 py-3">
          <span className="font-medium">
            You · <span className="text-gold">{account.rank ? `#${account.rank}` : "unranked"}</span>
          </span>
          <span className={`tabular font-semibold ${pnlColor(account.realizedPnlPct)}`}>{fmtPct(account.realizedPnlPct)}</span>
        </div>
      )}
      {live && (
        <p className="text-center text-xs text-muted">
          Live equity incl. open position: <span className={pnlColor(live.pnlPct)}>{fmtPct(live.pnlPct)}</span> — rankings update when positions close.
        </p>
      )}

      {feed.length > 0 && (
        <div className="rounded-2xl border border-line bg-panel">
          <div className="border-b border-line px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted">Live activity</div>
          <ul className="max-h-64 divide-y divide-line overflow-y-auto">
            {feed.map((f) => (
              <li key={f.id} className="flex items-center justify-between px-4 py-2 text-xs">
                <span className="truncate">
                  {f.kind === "LIQUIDATION" ? "💥" : f.kind === "WINNER" ? "🏆" : f.kind === "OPEN" ? "⚡" : "✅"} {f.name}
                  {f.side && (
                    <span className={f.side === "LONG" ? "text-up" : "text-down"}>
                      {" "}
                      {f.kind === "OPEN" ? "opened" : f.kind === "LIQUIDATION" ? "liquidated" : "closed"} {f.side} {f.leverage}×
                    </span>
                  )}
                </span>
                {typeof f.roePct === "number" && <span className={`tabular ${pnlColor(f.roePct)}`}>{fmtPct(f.roePct)}</span>}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
