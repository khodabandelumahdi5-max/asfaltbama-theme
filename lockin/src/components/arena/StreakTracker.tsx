import { CHALLENGE_DAYS } from "@/lib/constants";
import type { ProofCardData } from "@/lib/types";

type DayState = "verified" | "pending" | "rejected" | "missed" | "today" | "locked";

const CELL: Record<DayState, string> = {
  verified: "bg-acid text-obsidian border-acid",
  pending: "bg-obsidian-800 text-cyan border-cyan animate-flicker",
  rejected: "bg-fatal text-obsidian border-fatal",
  missed: "bg-obsidian text-fatal border-fatal border-dashed",
  today: "bg-obsidian-800 text-bone border-cyan shadow-brutal-cyan",
  locked: "bg-obsidian-900 text-obsidian-600 border-obsidian-700",
};

const LABEL: Record<DayState, string> = {
  verified: "Verified",
  pending: "Under review",
  rejected: "Rejected",
  missed: "Missed",
  today: "Due",
  locked: "Locked",
};

const SWATCH: Record<DayState, string> = {
  verified: "bg-acid border-acid",
  pending: "bg-obsidian-800 border-cyan",
  rejected: "bg-fatal border-fatal",
  missed: "bg-obsidian border-fatal border-dashed",
  today: "bg-obsidian-800 border-cyan",
  locked: "bg-obsidian-900 border-obsidian-700",
};

const LEGEND: DayState[] = ["verified", "pending", "rejected", "missed", "today", "locked"];

function dayState(
  day: number,
  currentDay: number,
  graceDay: number | null,
  eliminatedOnDay: number | null,
  proof: ProofCardData | undefined,
): DayState {
  if (proof) {
    if (proof.status === "VERIFIED") return "verified";
    if (proof.status === "REJECTED") return "rejected";
    return "pending";
  }
  if (eliminatedOnDay !== null && day > eliminatedOnDay) return "locked";
  if (day === graceDay) return "today";
  if (day < currentDay) return "missed";
  if (day === currentDay) return "today";
  return "locked";
}

export function StreakTracker({
  currentDay,
  graceDay,
  eliminatedOnDay,
  currentStreak,
  proofs,
}: {
  currentDay: number;
  graceDay: number | null;
  eliminatedOnDay: number | null;
  currentStreak: number;
  proofs: ProofCardData[];
}) {
  const byDay = new Map(proofs.map((p) => [p.dayNumber, p]));
  const days = Array.from({ length: CHALLENGE_DAYS }, (_, i) => i + 1);

  return (
    <section aria-labelledby="streak-heading" className="border-3 border-bone/90 bg-obsidian-900 p-5 sm:p-6">
      <header className="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="font-mono text-xs uppercase tracking-[0.3em] text-bone-muted">21-day protocol</p>
          <h2 id="streak-heading" className="font-display text-2xl font-bold uppercase text-bone">
            Streak tracker
          </h2>
        </div>
        <p className="font-mono text-bone">
          <span className="text-4xl font-bold text-acid tabular-nums">{currentStreak}</span>
          <span className="text-bone-muted"> / {CHALLENGE_DAYS} verified</span>
        </p>
      </header>

      <ol className="grid grid-cols-7 gap-2 sm:gap-3">
        {days.map((day) => {
          const state = dayState(day, currentDay, graceDay, eliminatedOnDay, byDay.get(day));
          return (
            <li
              key={day}
              title={`Day ${day}: ${LABEL[state]}`}
              aria-label={`Day ${day}: ${LABEL[state]}`}
              className={`flex aspect-square flex-col items-center justify-center border-3 font-mono transition-transform ${CELL[state]}`}
            >
              <span className="text-[10px] uppercase opacity-70">Day</span>
              <span className="text-lg font-bold leading-none tabular-nums sm:text-2xl">
                {String(day).padStart(2, "0")}
              </span>
            </li>
          );
        })}
      </ol>

      <ul className="mt-5 flex flex-wrap gap-x-4 gap-y-2 font-mono text-xs uppercase text-bone-muted">
        {LEGEND.map((s) => (
          <li key={s} className="flex items-center gap-2">
            <span className={`inline-block h-3 w-3 border-2 ${SWATCH[s]}`} />
            {LABEL[s]}
          </li>
        ))}
      </ul>
    </section>
  );
}
