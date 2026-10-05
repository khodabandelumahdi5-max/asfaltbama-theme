import { pct } from "../format";
import { useOverlay } from "../store";

export function EventFeed() {
  const feed = useOverlay((s) => s.feed);
  return (
    <div className="feed">
      <div className="panel__title">
        <span>⚡ LIVE ACTIVITY</span>
      </div>
      <ul className="feed__list">
        {feed.length === 0 && <li className="feed__item feed__item--muted">Trades will appear here in real time</li>}
        {feed.slice(0, 5).map((e) => (
          <li key={e.id} className={`feed__item feed__item--${e.kind.toLowerCase()}`}>
            <span className="feed__icon">{e.kind === "LIQUIDATION" ? "💥" : e.kind === "WINNER" ? "🏆" : e.kind === "OPEN" ? "🚀" : "✅"}</span>
            <span className="feed__name">{e.name}</span>
            <span className="feed__text">
              {e.kind === "OPEN" && e.side && (
                <span className={e.side === "LONG" ? "up" : "down"}>
                  {e.side} {e.leverage}×
                </span>
              )}
              {e.kind === "CLOSE" && typeof e.roePct === "number" && (
                <span className={e.roePct >= 0 ? "up" : "down"}>
                  closed {pct(e.roePct, 1)}
                </span>
              )}
              {e.kind === "LIQUIDATION" && <span className="down">LIQUIDATED {e.leverage}×</span>}
              {e.kind === "WINNER" && typeof e.pnl === "number" && <span className="gold">wins with {pct(e.pnl)}</span>}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}
