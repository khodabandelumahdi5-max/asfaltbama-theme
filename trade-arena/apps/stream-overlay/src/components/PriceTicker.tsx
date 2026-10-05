import { useEffect, useRef, useState } from "react";
import { SYMBOL_LABEL } from "@arena/shared";
import { useOverlay } from "../store";

const fmt = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export function PriceTicker() {
  const price = useOverlay((s) => s.price);
  const prev = useOverlay((s) => s.prevPrice);
  const candles = useOverlay((s) => s.candles);
  const [flash, setFlash] = useState(0);
  const dir = useRef<"up" | "down">("up");

  useEffect(() => {
    if (!prev || price === prev) return;
    dir.current = price > prev ? "up" : "down";
    setFlash((f) => f + 1);
  }, [price, prev]);

  const ref = candles[0]?.open ?? price;
  const change = ref ? ((price - ref) / ref) * 100 : 0;
  const high = candles.length ? Math.max(...candles.map((c) => c.high), price) : price;
  const low = candles.length ? Math.min(...candles.map((c) => c.low), price) : price;

  return (
    <div className="ticker">
      <div className="ticker__symbol">{SYMBOL_LABEL}</div>
      <div key={flash} className={`ticker__price ticker__price--${dir.current} flash-${dir.current}`}>
        {price ? fmt.format(price) : "—"}
      </div>
      <div className="ticker__stats">
        <span className={change >= 0 ? "up" : "down"}>
          {change >= 0 ? "▲" : "▼"} {Math.abs(change).toFixed(2)}%
        </span>
        <span>H {fmt.format(high)}</span>
        <span>L {fmt.format(low)}</span>
      </div>
    </div>
  );
}
