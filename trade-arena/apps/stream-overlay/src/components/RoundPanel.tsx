import { useEffect, useState } from "react";
import { useOverlay } from "../store";

function useClock(offset: number) {
  const [now, setNow] = useState(Date.now() + offset);
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now() + offset), 250);
    return () => clearInterval(id);
  }, [offset]);
  return now;
}

const mmss = (ms: number) => {
  const t = Math.max(0, Math.floor(ms / 1000));
  return `${String(Math.floor(t / 60)).padStart(2, "0")}:${String(t % 60).padStart(2, "0")}`;
};

export function RoundPanel() {
  const t = useOverlay((s) => s.tournament);
  const offset = useOverlay((s) => s.serverOffsetMs);
  const now = useClock(offset);
  if (!t) return <div className="round round--idle">Next round loading…</div>;

  const live = t.status === "ACTIVE";
  const remaining = live ? t.endsAt - now : t.startsAt - now;
  const total = t.endsAt - t.startsAt;
  const progress = live ? 1 - remaining / total : 0;
  const urgent = live && remaining < 60_000;

  return (
    <div className="round">
      <div className="round__head">
        <span className="round__name">{t.name}</span>
        <span className={`round__status round__status--${t.status.toLowerCase()}`}>{live ? "● LIVE" : t.status === "SCHEDULED" ? "STARTING" : t.status}</span>
      </div>
      <div className={`round__timer ${urgent ? "round__timer--urgent" : ""}`}>
        {t.status === "SETTLING" ? "PAYOUTS…" : t.status === "FINISHED" ? "FINISHED" : mmss(remaining)}
      </div>
      <div className="round__label">{live ? "until round ends" : t.status === "SCHEDULED" ? "until trading starts" : "next round soon"}</div>
      <div className="round__bar">
        <div className="round__bar-fill" style={{ width: `${Math.min(100, Math.max(0, progress * 100))}%` }} />
      </div>
      <div className="round__stats">
        <div><b>{t.players}</b><span>players</span></div>
        <div><b>{t.prizePoolTickets} 🎟</b><span>prize pool</span></div>
        <div><b>{t.startingBalance.toLocaleString()}$</b><span>start balance</span></div>
      </div>
    </div>
  );
}
