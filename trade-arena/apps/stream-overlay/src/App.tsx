import { useEffect, useState } from "react";
import { CAPTURE, LAYOUT, STAGE } from "./config";
import { connectSpectator } from "./socket";
import { Brand } from "./components/Brand";
import { PriceTicker } from "./components/PriceTicker";
import { LiveChart } from "./components/LiveChart";
import { Leaderboard } from "./components/Leaderboard";
import { QrPanel } from "./components/QrPanel";
import { RoundPanel } from "./components/RoundPanel";
import { EventFeed } from "./components/EventFeed";
import { WinnerBanner } from "./components/WinnerBanner";

/** Scales the fixed-size stage into any preview window; capture mode renders 1:1. */
function useStageScale(): number {
  const compute = () => (CAPTURE ? 1 : Math.min(window.innerWidth / STAGE.width, window.innerHeight / STAGE.height));
  const [scale, setScale] = useState(compute);
  useEffect(() => {
    const onResize = () => setScale(compute());
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);
  return scale;
}

export function App() {
  const scale = useStageScale();
  useEffect(() => {
    connectSpectator();
  }, []);

  return (
    <div className="viewport">
      <div
        className={`stage stage--${LAYOUT} ${CAPTURE ? "stage--capture" : ""}`}
        style={{ width: STAGE.width, height: STAGE.height, transform: `scale(${scale})` }}
      >
        <div className="bg-grid" />
        <div className="bg-glow" />
        <header className="area-brand">
          <Brand />
        </header>
        <section className="area-price panel">
          <PriceTicker />
        </section>
        <section className="area-chart panel">
          <LiveChart />
        </section>
        <section className="area-round panel">
          <RoundPanel />
        </section>
        <section className="area-board panel">
          <Leaderboard />
        </section>
        <section className="area-qr panel panel--accent">
          <QrPanel />
        </section>
        <section className="area-feed panel">
          <EventFeed />
        </section>
        <WinnerBanner />
      </div>
    </div>
  );
}
