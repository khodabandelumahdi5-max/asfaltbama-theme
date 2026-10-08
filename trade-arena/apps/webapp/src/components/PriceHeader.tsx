"use client";
import { useEffect, useRef, useState } from "react";
import { SYMBOL_LABEL } from "@arena/shared";
import { useMarket } from "@/store/market";
import { fmtPct, fmtPrice } from "@/lib/format";

export function PriceHeader() {
  const price = useMarket((s) => s.price);
  const prev = useMarket((s) => s.prevPrice);
  const candles = useMarket((s) => s.candles);
  const [flash, setFlash] = useState<"" | "flash-up" | "flash-down">("");
  const key = useRef(0);

  useEffect(() => {
    if (!prev || price === prev) return;
    key.current++;
    setFlash(price > prev ? "flash-up" : "flash-down");
  }, [price, prev]);

  const ref = candles[0]?.open ?? price;
  const change = ref ? ((price - ref) / ref) * 100 : 0;

  return (
    <div className="flex items-end justify-between px-4 pt-3">
      <div>
        <div className="text-xs font-medium text-muted">{SYMBOL_LABEL} · Perp (sim)</div>
        <div key={key.current} data-testid="price" className={`tabular -mx-1 rounded px-1 font-mono text-3xl font-bold ${price >= prev ? "text-up" : "text-down"} ${flash}`}>
          {price ? fmtPrice(price) : "—"}
        </div>
      </div>
      <div className={`tabular pb-1 text-sm font-semibold ${change >= 0 ? "text-up" : "text-down"}`}>{fmtPct(change)}</div>
    </div>
  );
}
