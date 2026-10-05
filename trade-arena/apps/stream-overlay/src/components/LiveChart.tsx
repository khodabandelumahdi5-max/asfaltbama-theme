import { useEffect, useRef } from "react";
import { AreaSeries, CandlestickSeries, ColorType, createChart, type ISeriesApi, type UTCTimestamp } from "lightweight-charts";
import type { Candle } from "@arena/shared";
import { useOverlay } from "../store";

const bar = (c: Candle) => ({ time: c.time as UTCTimestamp, open: c.open, high: c.high, low: c.low, close: c.close });

/** Candles + a glowing close-price area underlay; ticks are applied imperatively (no React re-render). */
export function LiveChart() {
  const ref = useRef<HTMLDivElement>(null);
  const candles = useOverlay((s) => s.candles);
  const seriesRef = useRef<{ candles: ISeriesApi<"Candlestick">; area: ISeriesApi<"Area"> } | null>(null);
  const lastTime = useRef(0);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const chart = createChart(el, {
      autoSize: true,
      layout: {
        background: { type: ColorType.Solid, color: "transparent" },
        textColor: "#8b93a7",
        fontSize: 22,
        fontFamily: "JetBrains Mono, monospace",
        attributionLogo: false,
      },
      grid: { vertLines: { visible: false }, horzLines: { color: "rgba(255,255,255,0.05)" } },
      rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.1, bottom: 0.08 } },
      timeScale: { borderVisible: false, timeVisible: true, secondsVisible: true, rightOffset: 6, barSpacing: 14 },
      crosshair: { vertLine: { visible: false }, horzLine: { visible: false } },
      handleScroll: false,
      handleScale: false,
    });
    const area = chart.addSeries(AreaSeries, {
      lineColor: "rgba(240,185,11,0.0)",
      topColor: "rgba(240,185,11,0.18)",
      bottomColor: "rgba(240,185,11,0.0)",
      lineWidth: 1,
      priceLineVisible: false,
      lastValueVisible: false,
      crosshairMarkerVisible: false,
    });
    const series = chart.addSeries(CandlestickSeries, {
      upColor: "#00e6a0",
      downColor: "#ff3b5c",
      borderVisible: false,
      wickUpColor: "#00e6a0",
      wickDownColor: "#ff3b5c",
      priceLineColor: "#f0b90b",
      priceLineWidth: 2,
    });
    seriesRef.current = { candles: series, area };

    const unsub = useOverlay.subscribe((s, p) => {
      const t = s.lastTick;
      if (!t || t === p.lastTick || t.candle.time < lastTime.current) return;
      series.update(bar(t.candle));
      area.update({ time: t.candle.time as UTCTimestamp, value: t.candle.close });
      lastTime.current = t.candle.time;
    });
    return () => {
      unsub();
      chart.remove();
      seriesRef.current = null;
    };
  }, []);

  useEffect(() => {
    const s = seriesRef.current;
    if (!s || candles.length === 0) return;
    s.candles.setData(candles.map(bar));
    s.area.setData(candles.map((c) => ({ time: c.time as UTCTimestamp, value: c.close })));
    lastTime.current = candles[candles.length - 1]!.time;
  }, [candles]);

  return <div ref={ref} className="chart" />;
}
