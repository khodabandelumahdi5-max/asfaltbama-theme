"use client";
import { useSession } from "@/store/session";
import { useMarket } from "@/store/market";
import { useServerNow } from "@/hooks/useNow";
import { fmtCountdown } from "@/lib/format";

const STATUS_STYLE: Record<string, string> = {
  ACTIVE: "bg-up/15 text-up",
  SCHEDULED: "bg-gold/15 text-gold",
  SETTLING: "bg-accent/15 text-accent",
  FINISHED: "bg-line text-muted",
};

export function Header() {
  const tournament = useSession((s) => s.tournament);
  const tickets = useSession((s) => s.user?.tickets ?? 0);
  const online = useSession((s) => s.online);
  const connection = useMarket((s) => s.connection);
  const now = useServerNow(500);

  let timer = "";
  if (tournament?.status === "SCHEDULED") timer = `starts in ${fmtCountdown(tournament.startsAt - now)}`;
  else if (tournament?.status === "ACTIVE") timer = fmtCountdown(tournament.endsAt - now);
  else if (tournament?.status === "SETTLING") timer = "settling…";

  return (
    <header className="sticky top-0 z-10 flex items-center justify-between border-b border-line bg-bg/95 px-4 py-2.5 backdrop-blur">
      <div className="min-w-0">
        <div className="flex items-center gap-2">
          <span className="truncate text-sm font-semibold">{tournament?.name ?? "Trade Arena"}</span>
          {tournament && (
            <span className={`rounded px-1.5 py-0.5 text-[10px] font-bold tracking-wide ${STATUS_STYLE[tournament.status]}`}>
              {tournament.status === "ACTIVE" ? "LIVE" : tournament.status}
            </span>
          )}
        </div>
        <div className="tabular text-xs text-muted">
          {timer && <span className={tournament?.status === "ACTIVE" && tournament.endsAt - now < 60_000 ? "text-down" : ""}>{timer}</span>}
          {tournament && <span> · {tournament.players} players · pool {tournament.prizePoolTickets} 🎟</span>}
        </div>
      </div>
      <div className="flex shrink-0 items-center gap-3">
        <div className="flex items-center gap-1 text-xs text-muted" title={`${online} online`}>
          <span className={`size-2 rounded-full ${connection === "online" ? "bg-up" : "animate-pulse bg-gold"}`} />
          {online > 0 && <span className="tabular">{online}</span>}
        </div>
        <div className="rounded-lg bg-panel-2 px-2.5 py-1 text-sm font-semibold tabular">{tickets} 🎟</div>
      </div>
    </header>
  );
}
