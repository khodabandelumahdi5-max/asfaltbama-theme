"use client";
import { useCallback, useEffect, useState } from "react";
import type { DepositNetwork, DepositView } from "@arena/shared";
import { useSession } from "@/store/session";
import { api, type MeResponse } from "@/lib/api";
import { haptic, tg } from "@/lib/telegram";
import { toast } from "@/store/toast";

const PACKS = [1, 5, 10, 25];
const STATUS_STYLE: Record<DepositView["status"], string> = {
  PENDING: "text-gold",
  CONFIRMED: "text-up",
  EXPIRED: "text-muted",
  UNDERPAID: "text-down",
};

function copy(text: string, label: string) {
  void navigator.clipboard
    ?.writeText(text)
    .then(() => {
      haptic.notify("success");
      toast.success(`${label} copied`);
    })
    .catch(() => toast.error("Copy failed"));
}

function CopyRow({ label, value, mono = true }: { label: string; value: string; mono?: boolean }) {
  return (
    <button onClick={() => copy(value, label)} className="flex w-full items-center justify-between gap-3 rounded-xl bg-panel-2 px-3 py-2 text-left">
      <span className="min-w-0">
        <span className="block text-[10px] uppercase tracking-wide text-muted">{label}</span>
        <span className={`block break-all text-sm ${mono ? "font-mono" : ""}`}>{value}</span>
      </span>
      <span className="shrink-0 text-xs text-gold">Copy</span>
    </button>
  );
}

export function WalletPanel() {
  const user = useSession((s) => s.user);
  const [network, setNetwork] = useState<DepositNetwork>("TRC20");
  const [pack, setPack] = useState(5);
  const [active, setActive] = useState<DepositView | null>(null);
  const [history, setHistory] = useState<DepositView[]>([]);
  const [me, setMe] = useState<MeResponse | null>(null);
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    try {
      const [deps, profile] = await Promise.all([api.deposits(), api.me()]);
      setHistory(deps);
      setMe(profile);
      setActive((cur) => (cur ? (deps.find((d) => d.id === cur.id) ?? cur) : (deps.find((d) => d.status === "PENDING") ?? null)));
    } catch (err) {
      toast.error((err as Error).message);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh, user?.tickets]);

  const create = async () => {
    setBusy(true);
    try {
      const d = await api.createDeposit(network, pack);
      setActive(d);
      haptic.notify("success");
      void refresh();
    } catch (err) {
      toast.error((err as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const share = () => {
    if (!me) return;
    const url = `https://t.me/share/url?url=${encodeURIComponent(me.referralLink)}&text=${encodeURIComponent("Join me in Trade Arena ⚡️ fast BTC trading tournaments")}`;
    const app = tg();
    if (app) app.openTelegramLink(url);
    else window.open(url, "_blank");
  };

  return (
    <div className="space-y-4 p-4">
      <div className="rounded-2xl border border-line bg-panel p-4 text-center">
        <div className="text-xs uppercase tracking-wide text-muted">Tickets</div>
        <div className="tabular text-4xl font-bold">{user?.tickets ?? 0} 🎟</div>
        <div className="mt-1 text-xs text-muted">1 ticket = 1 tournament entry</div>
      </div>

      <div className="space-y-3 rounded-2xl border border-line bg-panel p-4">
        <div className="text-sm font-semibold">Buy tickets with USDT</div>
        <div className="grid grid-cols-2 gap-2">
          {(["TRC20", "TON"] as const).map((n) => (
            <button
              key={n}
              onClick={() => setNetwork(n)}
              className={`h-10 rounded-xl text-sm font-semibold ${network === n ? "bg-gold text-black" : "bg-panel-2 text-muted"}`}
            >
              {n === "TRC20" ? "USDT · TRC20" : "USDT · TON"}
            </button>
          ))}
        </div>
        <div className="grid grid-cols-4 gap-2">
          {PACKS.map((p) => (
            <button
              key={p}
              onClick={() => setPack(p)}
              className={`h-10 rounded-xl text-sm font-semibold ${pack === p ? "bg-text text-bg" : "bg-panel-2 text-muted"}`}
            >
              {p} 🎟
            </button>
          ))}
        </div>
        <button disabled={busy} onClick={create} className="h-12 w-full rounded-xl bg-gold font-bold text-black disabled:opacity-50">
          {busy ? "Creating…" : `Get deposit address`}
        </button>
      </div>

      {active && (
        <div className="space-y-2 rounded-2xl border border-gold/40 bg-panel p-4">
          <div className="flex items-center justify-between">
            <span className="text-sm font-semibold">
              Deposit {active.tickets} 🎟 · {active.network}
            </span>
            <span className={`text-xs font-bold ${STATUS_STYLE[active.status]}`}>{active.status}</span>
          </div>
          <CopyRow label="Amount (send exactly)" value={`${active.amount}`} />
          <CopyRow label="Address" value={active.address} />
          {active.memo && <CopyRow label="Comment / memo (required)" value={active.memo} />}
          <CopyRow label="Reference" value={active.reference} />
          <p className="text-xs text-muted">
            Tickets are credited automatically after on-chain confirmation. Expires {new Date(active.expiresAt).toLocaleTimeString()}.
          </p>
        </div>
      )}

      <div className="space-y-2 rounded-2xl border border-line bg-panel p-4">
        <div className="flex items-center justify-between">
          <span className="text-sm font-semibold">Invite friends 🎁</span>
          <span className="text-xs text-muted">{user?.referralCount ?? 0} joined</span>
        </div>
        <p className="text-xs text-muted">Earn a bonus ticket when a friend makes their first deposit.</p>
        {me && <CopyRow label="Your link" value={me.referralLink} />}
        <button onClick={share} className="h-11 w-full rounded-xl border border-gold font-semibold text-gold">
          Share invite
        </button>
      </div>

      {history.length > 0 && (
        <div className="rounded-2xl border border-line bg-panel">
          <div className="border-b border-line px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted">Deposit history</div>
          <ul className="divide-y divide-line">
            {history.map((d) => (
              <li key={d.id}>
                <button onClick={() => setActive(d)} className="flex w-full items-center justify-between px-4 py-2 text-left text-sm">
                  <span>
                    <span className="font-mono">{d.reference}</span>
                    <span className="ml-2 text-xs text-muted">
                      {d.amount} · {d.network}
                    </span>
                  </span>
                  <span className={`text-xs font-bold ${STATUS_STYLE[d.status]}`}>{d.status}</span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
