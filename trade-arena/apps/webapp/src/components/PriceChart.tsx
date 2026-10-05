"use client";
import { useEffect, useRef } from "react";
import {
  CandlestickSeries,
  ColorType,
  CrosshairMode,
  LineStyle,
  createChart,
  type IChartApi,
  type IPriceLine,
  type ISeriesApi,
  type UTCTimestamp,
} from "lightweight-charts";
import type { Candle } from "@arena/shared";
import { useMarket } from "@/store/market";
import { useSession } from "@/store/session";

const toBar = (c: Candle) => ({ time: c.time as UTCTimestamp, open: c.open, high: c.high, low: c.low, close: c.close });

/**
 * Canvas candlestick chart. Live ticks bypass React entirely: we subscribe to the Zustand
 * store and call `series.update()` directly, so 4+ ticks/sec cost no re-renders.
 */
export function PriceChart({ height = 260 }: { height?: number }) {
  const containerRef = useRef<HTMLDivElement>(null);
  const chartRef = useRef<IChartApi | null>(null);
  const seriesRef = useRef<ISeriesApi<"Candlestick"> | null>(null);
  const lastTimeRef = useRef(0);
  const linesRef = useRef<IPriceLine[]>([]);
  const candles = useMarket((s) => s.candles);
  const position = useSession((s) => s.position);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    const chart = createChart(el, {
      autoSize: true,
      layout: { background: { type: ColorType.Solid, color: "transparent" }, textColor: "#7d8597", fontSize: 11, attributionLogo: false },
      grid: { vertLines: { color: "rgba(34,42,58,0.5)" }, horzLines: { color: "rgba(34,42,58,0.5)" } },
      rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.12, bottom: 0.08 } },
      timeScale: { borderVisible: false, timeVisible: true, secondsVisible: true, rightOffset: 4, barSpacing: 7 },
      crosshair: { mode: CrosshairMode.Magnet },
      handleScale: { axisPressedMouseMove: false },
    });
    const series = chart.addSeries(CandlestickSeries, {
      upColor: "#16c784",
      downColor: "#ea3943",
      borderVisible: false,
      wickUpColor: "#16c784",
      wickDownColor: "#ea3943",
      priceFormat: { type: "price", precision: 2, minMove: 0.01 },
    });
    chartRef.current = chart;
    seriesRef.current = series;

    const unsubscribe = useMarket.subscribe((state, prev) => {
      const tick = state.lastTick;
      if (!tick || tick === prev.lastTick || tick.candle.time < lastTimeRef.current) return;
      series.update(toBar(tick.candle));
      lastTimeRef.current = tick.candle.time;
    });

    return () => {
      unsubscribe();
      chart.remove();
      chartRef.current = null;
      seriesRef.current = null;
    };
  }, []);

  // (Re)seed history after every session sync.
  useEffect(() => {
    const series = seriesRef.current;
    if (!series || candles.length === 0) return;
    series.setData(candles.map(toBar));
    lastTimeRef.current = candles[candles.length - 1]!.time;
    chartRef.current?.timeScale().scrollToRealTime();
  }, [candles]);

  // Entry / liquidation guide lines for the open position.
  useEffect(() => {
    const series = seriesRef.current;
    if (!series) return;
    for (const l of linesRef.current) series.removePriceLine(l);
    linesRef.current = [];
    if (!position) return;
    const color = position.side === "LONG" ? "#16c784" : "#ea3943";
    linesRef.current.push(
      series.createPriceLine({ price: position.entryPrice, color, lineWidth: 1, lineStyle: LineStyle.Dashed, axisLabelVisible: true, title: `${position.side} ${position.leverage}×` }),
      series.createPriceLine({ price: position.liquidationPrice, color: "#f0b90b", lineWidth: 1, lineStyle: LineStyle.Dotted, axisLabelVisible: true, title: "LIQ" }),
    );
  }, [position]);

  return <div ref={containerRef} style={{ height }} className="w-full" />;
}
