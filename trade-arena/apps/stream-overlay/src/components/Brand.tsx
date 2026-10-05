import { useOverlay } from "../store";

export function Brand() {
  const connected = useOverlay((s) => s.connected);
  const online = useOverlay((s) => s.online);
  return (
    <div className="brand">
      <div className="brand__logo">
        <span className="brand__bolt">⚡</span>
        TRADE<span className="brand__accent">ARENA</span>
      </div>
      <div className="brand__meta">
        <span className={`live-badge ${connected ? "" : "live-badge--off"}`}>
          <span className="live-badge__dot" />
          {connected ? "LIVE" : "RECONNECTING"}
        </span>
        <span className="brand__online">{online.toLocaleString()} watching & trading</span>
      </div>
    </div>
  );
}
