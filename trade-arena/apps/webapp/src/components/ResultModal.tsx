"use client";
import { useSession } from "@/store/session";
import { fmtPct } from "@/lib/format";

export function ResultModal() {
  const result = useSession((s) => s.lastResult);
  const me = useSession((s) => s.user?.id);
  const setResult = useSession((s) => s.setResult);
  if (!result) return null;
  const medals = ["🥇", "🥈", "🥉"];
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center" onClick={() => setResult(null)}>
      <div className="w-full max-w-md rounded-2xl border border-line bg-panel p-5" onClick={(e) => e.stopPropagation()}>
        <div className="text-center">
          <div className="text-3xl">🏁</div>
          <h2 className="mt-1 text-lg font-bold">{result.name} finished</h2>
          <p className="text-sm text-muted">Next round starts shortly</p>
        </div>
        <ol className="mt-4 space-y-2">
          {result.winners.length === 0 && <li className="text-center text-sm text-muted">No traders this round.</li>}
          {result.winners.map((w) => (
            <li
              key={w.userId}
              className={`flex items-center justify-between rounded-xl px-3 py-2 ${w.userId === me ? "bg-gold/15 ring-1 ring-gold/40" : "bg-panel-2"}`}
            >
              <span className="flex items-center gap-2 font-medium">
                <span className="text-xl">{medals[w.rank - 1]}</span>
                {w.name}
              </span>
              <span className="text-right">
                <span className={`tabular font-semibold ${w.pnlPct >= 0 ? "text-up" : "text-down"}`}>{fmtPct(w.pnlPct)}</span>
                {w.prizeTickets > 0 && <span className="ml-2 text-sm text-gold">+{w.prizeTickets} 🎟</span>}
              </span>
            </li>
          ))}
        </ol>
        <button onClick={() => setResult(null)} className="mt-5 h-11 w-full rounded-xl bg-gold font-semibold text-black">
          Continue
        </button>
      </div>
    </div>
  );
}
