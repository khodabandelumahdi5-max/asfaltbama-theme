"use client";
import { useState } from "react";
import { useSession } from "@/store/session";
import { joinTournament } from "@/lib/socket";
import { fmtUsd } from "@/lib/format";

export function JoinCard({ onNeedTickets }: { onNeedTickets?: () => void }) {
  const tournament = useSession((s) => s.tournament);
  const tickets = useSession((s) => s.user?.tickets ?? 0);
  const [busy, setBusy] = useState(false);
  if (!tournament) return null;
  const open = tournament.status === "SCHEDULED" || tournament.status === "ACTIVE";
  const enough = tickets >= tournament.entryTickets;

  return (
    <div className="mx-4 rounded-2xl border border-gold/30 bg-gradient-to-b from-gold/10 to-transparent p-4">
      <div className="text-base font-bold">Join {tournament.name}</div>
      <ul className="mt-2 space-y-1 text-sm text-muted">
        <li>• Virtual balance: <span className="text-text">{fmtUsd(tournament.startingBalance)}</span></li>
        <li>• Entry: <span className="text-text">{tournament.entryTickets === 0 ? "Free" : `${tournament.entryTickets} 🎟`}</span></li>
        <li>• Prize pool: <span className="text-gold">{tournament.prizePoolTickets} 🎟</span> split 50/30/20</li>
      </ul>
      {!open ? (
        <div className="mt-3 text-sm text-muted">Registration closed — the next round opens in a moment.</div>
      ) : enough ? (
        <button
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            await joinTournament();
            setBusy(false);
          }}
          className="mt-4 h-12 w-full rounded-xl bg-gold text-base font-bold text-black active:scale-[0.99] disabled:opacity-60"
        >
          {busy ? "Joining…" : `Join round · ${tournament.entryTickets} 🎟`}
        </button>
      ) : (
        <button onClick={onNeedTickets} className="mt-4 h-12 w-full rounded-xl border border-gold text-base font-bold text-gold">
          Get tickets to join
        </button>
      )}
    </div>
  );
}
