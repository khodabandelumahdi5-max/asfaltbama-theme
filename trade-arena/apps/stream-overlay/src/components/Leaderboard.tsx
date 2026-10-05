import { LAYOUT } from "../config";
import { pct } from "../format";
import { useOverlay } from "../store";

const MEDALS = ["🥇", "🥈", "🥉"];
const ROW_H = LAYOUT === "portrait" ? 84 : 82;

/**
 * Rows are absolutely positioned by rank and keyed by user id, so a re-rank animates as a
 * smooth vertical slide (CSS transform transition) instead of a hard re-order.
 */
export function Leaderboard() {
  const lb = useOverlay((s) => s.leaderboard);
  const entries = lb?.entries ?? [];
  return (
    <div className="board">
      <div className="panel__title">
        <span>🏆 TOP 10 TRADERS</span>
        <span className="panel__sub">{lb?.players ?? 0} in round</span>
      </div>
      <div className="board__rows" style={{ height: ROW_H * 10 }}>
        {entries.length === 0 && <div className="board__empty">Waiting for the first trades…<br />Scan the QR to be #1</div>}
        {entries.map((e) => (
          <div
            key={e.userId}
            className={`board__row ${e.rank <= 3 ? `board__row--top board__row--r${e.rank}` : ""}`}
            style={{ transform: `translateY(${(e.rank - 1) * ROW_H}px)`, height: ROW_H - 10 }}
          >
            <span className="board__rank">{MEDALS[e.rank - 1] ?? e.rank}</span>
            <span className="board__name">{e.name}</span>
            <span className={`board__pnl ${e.pnlPct >= 0 ? "up" : "down"}`}>{pct(e.pnlPct)}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
