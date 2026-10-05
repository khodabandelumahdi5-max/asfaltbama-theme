"use client";
import { useEffect, useState } from "react";
import { initTelegram } from "@/lib/telegram";
import { connectSocket } from "@/lib/socket";
import { useMarket } from "@/store/market";
import { useSession } from "@/store/session";
import { Header } from "./Header";
import { TradeView } from "./TradeView";
import { Leaderboard } from "./Leaderboard";
import { WalletPanel } from "./WalletPanel";
import { Toasts } from "./Toasts";
import { ResultModal } from "./ResultModal";

type Tab = "trade" | "ranks" | "wallet";

const TABS: Array<{ id: Tab; label: string; icon: string }> = [
  { id: "trade", label: "Trade", icon: "⚡" },
  { id: "ranks", label: "Ranks", icon: "🏆" },
  { id: "wallet", label: "Wallet", icon: "🎟" },
];

export function ArenaApp() {
  const [tab, setTab] = useState<Tab>("trade");
  const ready = useSession((s) => s.ready);
  const connection = useMarket((s) => s.connection);

  useEffect(() => {
    initTelegram();
    connectSocket();
  }, []);

  if (connection === "unauthorized") {
    return (
      <main className="flex min-h-dvh flex-col items-center justify-center gap-3 p-8 text-center">
        <div className="text-4xl">🔒</div>
        <h1 className="text-lg font-semibold">Open Trade Arena from Telegram</h1>
        <p className="text-sm text-muted">Your session couldn't be verified. Close and relaunch the Mini App from the bot.</p>
      </main>
    );
  }

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col pb-[calc(64px+env(safe-area-inset-bottom))]">
      <Header />
      {!ready ? (
        <div className="flex flex-1 items-center justify-center text-sm text-muted">
          <span className="mr-2 inline-block size-2 animate-ping rounded-full bg-gold" /> Connecting to the arena…
        </div>
      ) : (
        <div className="flex-1">
          {tab === "trade" && <TradeView onNeedTickets={() => setTab("wallet")} />}
          {tab === "ranks" && <Leaderboard />}
          {tab === "wallet" && <WalletPanel />}
        </div>
      )}
      <nav className="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-bg/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
        <div className="mx-auto grid max-w-md grid-cols-3">
          {TABS.map((t) => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={`flex h-16 flex-col items-center justify-center gap-0.5 text-xs font-medium transition-colors ${
                tab === t.id ? "text-gold" : "text-muted"
              }`}
            >
              <span className="text-lg leading-none">{t.icon}</span>
              {t.label}
            </button>
          ))}
        </div>
      </nav>
      <Toasts />
      <ResultModal />
    </main>
  );
}
