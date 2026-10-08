"use client";
import { useMemo, useState } from "react";
import { MAX_LEVERAGE, MIN_MARGIN, TAKER_FEE_RATE, liquidationPrice, type Side } from "@arena/shared";
import { useSession } from "@/store/session";
import { useMarket } from "@/store/market";
import { openTrade } from "@/lib/socket";
import { haptic } from "@/lib/telegram";
import { fmtPrice, fmtUsd } from "@/lib/format";

const LEVERAGE_PRESETS = [5, 10, 25, 50, 100];
const SIZE_PRESETS = [0.1, 0.25, 0.5, 1];

export function OrderPanel() {
  const account = useSession((s) => s.account);
  const status = useSession((s) => s.tournament?.status);
  const price = useMarket((s) => s.price);
  const [leverage, setLeverage] = useState(20);
  const [marginInput, setMarginInput] = useState("1000");
  const [busy, setBusy] = useState<Side | null>(null);

  const balance = account?.balance ?? 0;
  // Max margin such that margin + fee(margin * lev) <= balance.
  const maxMargin = Math.floor((balance / (1 + leverage * TAKER_FEE_RATE)) * 100) / 100;
  const margin = Number(marginInput) || 0;
  const notional = margin * leverage;
  const fee = notional * TAKER_FEE_RATE;
  const valid = margin >= MIN_MARGIN && margin <= maxMargin;
  const live = status === "ACTIVE";

  const liq = useMemo(
    () => ({ long: liquidationPrice("LONG", price, leverage), short: liquidationPrice("SHORT", price, leverage) }),
    [price, leverage],
  );

  const submit = async (side: Side) => {
    if (!valid || !live || busy) return;
    setBusy(side);
    await openTrade({ side, leverage, margin: Math.round(margin * 100) / 100 });
    setBusy(null);
  };

  return (
    <div className="mx-4 space-y-4 rounded-2xl border border-line bg-panel p-4">
      <div>
        <div className="mb-2 flex items-center justify-between text-xs text-muted">
          <span>Leverage</span>
          <span className="tabular text-base font-bold text-gold">{leverage}×</span>
        </div>
        <input
          type="range"
          min={1}
          max={MAX_LEVERAGE}
          value={leverage}
          onChange={(e) => {
            setLeverage(Number(e.target.value));
            haptic.select();
          }}
          className="w-full"
          aria-label="Leverage"
        />
        <div className="mt-2 grid grid-cols-5 gap-1.5">
          {LEVERAGE_PRESETS.map((l) => (
            <button
              key={l}
              onClick={() => {
                setLeverage(l);
                haptic.select();
              }}
              className={`h-8 rounded-lg text-xs font-semibold ${leverage === l ? "bg-gold text-black" : "bg-panel-2 text-muted"}`}
            >
              {l}×
            </button>
          ))}
        </div>
      </div>

      <div>
        <div className="mb-2 flex items-center justify-between text-xs text-muted">
          <span>Margin (USDT)</span>
          <span className="tabular">Avail {fmtUsd(balance)}</span>
        </div>
        <input
          inputMode="decimal"
          value={marginInput}
          onChange={(e) => setMarginInput(e.target.value.replace(/[^0-9.]/g, ""))}
          className={`tabular h-11 w-full rounded-xl border bg-panel-2 px-3 text-lg font-semibold outline-none focus:border-gold ${
            margin > 0 && !valid ? "border-down" : "border-line"
          }`}
          aria-label="Margin"
        />
        <div className="mt-2 grid grid-cols-4 gap-1.5">
          {SIZE_PRESETS.map((p) => (
            <button
              key={p}
              onClick={() => {
                setMarginInput(String(Math.floor(maxMargin * p * 100) / 100));
                haptic.select();
              }}
              className="h-8 rounded-lg bg-panel-2 text-xs font-semibold text-muted"
            >
              {p === 1 ? "MAX" : `${p * 100}%`}
            </button>
          ))}
        </div>
      </div>

      <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
        <dt className="text-muted">Position size</dt>
        <dd className="tabular text-right">{fmtUsd(notional)}</dd>
        <dt className="text-muted">Fee (0.04%)</dt>
        <dd className="tabular text-right">{fmtUsd(fee)}</dd>
        <dt className="text-muted">Liq. long / short</dt>
        <dd className="tabular text-right">
          <span className="text-up">{fmtPrice(liq.long)}</span> / <span className="text-down">{fmtPrice(liq.short)}</span>
        </dd>
      </dl>

      <div className="grid grid-cols-2 gap-3">
        <button
          disabled={!valid || !live || busy !== null}
          data-testid="long"
          onClick={() => submit("LONG")}
          className="h-14 rounded-xl bg-up text-base font-bold text-black active:scale-[0.98] disabled:opacity-40"
        >
          {busy === "LONG" ? "…" : "LONG ▲"}
        </button>
        <button
          disabled={!valid || !live || busy !== null}
          data-testid="short"
          onClick={() => submit("SHORT")}
          className="h-14 rounded-xl bg-down text-base font-bold text-white active:scale-[0.98] disabled:opacity-40"
        >
          {busy === "SHORT" ? "…" : "SHORT ▼"}
        </button>
      </div>
      {!live && <p className="text-center text-xs text-muted">Trading opens when the round goes LIVE.</p>}
      {live && margin > 0 && margin < MIN_MARGIN && <p className="text-center text-xs text-down">Minimum margin is {MIN_MARGIN} USDT.</p>}
    </div>
  );
}
