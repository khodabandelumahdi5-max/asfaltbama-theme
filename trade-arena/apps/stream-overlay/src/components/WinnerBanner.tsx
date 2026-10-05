import { pct } from "../format";
import { useOverlay } from "../store";

const MEDALS = ["🥇", "🥈", "🥉"];

export function WinnerBanner() {
  const result = useOverlay((s) => s.result);
  if (!result || result.winners.length === 0) return null;
  return (
    <div className="winner">
      <div className="winner__card">
        <div className="winner__kicker">{result.name} · FINAL RESULTS</div>
        {result.winners.map((w) => (
          <div key={w.userId} className={`winner__row winner__row--${w.rank}`}>
            <span className="winner__medal">{MEDALS[w.rank - 1]}</span>
            <span className="winner__name">{w.name}</span>
            <span className={`winner__pnl ${w.pnlPct >= 0 ? "up" : "down"}`}>{pct(w.pnlPct)}</span>
            {w.prizeTickets > 0 && <span className="winner__prize">+{w.prizeTickets} 🎟</span>}
          </div>
        ))}
      </div>
    </div>
  );
}
