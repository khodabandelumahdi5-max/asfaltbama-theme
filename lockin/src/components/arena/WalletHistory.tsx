import { LAMPORTS_PER_SOL } from "@solana/web3.js";
import { explorerTxUrl } from "@/lib/constants";
import type { PoolResult, RefundView } from "@/lib/types";

function sol(lamports: string | number, digits = 4) {
  return (Number(lamports) / LAMPORTS_PER_SOL).toFixed(digits);
}

function TxLink({ sig, label }: { sig: string; label: string }) {
  return (
    <a
      href={explorerTxUrl(sig)}
      target="_blank"
      rel="noreferrer"
      className="font-mono text-xs uppercase text-cyan underline decoration-2 underline-offset-4 hover:text-bone"
    >
      {label} ↗
    </a>
  );
}

const PAYOUT_STATE: Record<"PENDING" | "SENT" | "CONFIRMED", { label: string; tone: string }> = {
  PENDING: { label: "Payout queued", tone: "text-bone-muted" },
  SENT: { label: "Sending…", tone: "text-cyan animate-flicker" },
  CONFIRMED: { label: "Paid", tone: "text-acid" },
};

function ResultCard({ r }: { r: PoolResult }) {
  const s = r.settlement;
  const verdict = r.survived ? "Survived" : "Eliminated";

  return (
    <article
      className={`flex flex-col border-3 bg-obsidian-900 ${r.survived ? "border-acid shadow-brutal" : "border-fatal shadow-brutal-fatal"}`}
    >
      <header className="flex items-start justify-between gap-3 border-b-3 border-bone/20 p-4">
        <div className="min-w-0">
          <p className="font-mono text-[11px] uppercase tracking-[0.2em] text-bone-muted">
            {r.startDate} → {r.endDate}
          </p>
          <h3 className="truncate font-display text-xl font-bold uppercase text-bone">{r.title}</h3>
        </div>
        <span
          className={`shrink-0 px-2 py-1 font-mono text-[11px] font-bold uppercase ${s ? "bg-bone text-obsidian" : "bg-obsidian-700 text-bone"}`}
        >
          {s ? "Settled" : "Settling"}
        </span>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4">
        <div>
          <p className={`font-display text-4xl font-black uppercase leading-none ${r.survived ? "text-acid" : "text-fatal"}`}>
            {verdict}
          </p>
          <p className="mt-1 font-mono text-xs text-bone-muted">
            {r.survived
              ? `${r.currentStreak}/21 days verified`
              : r.eliminationReason === "MISSED_DEADLINE"
                ? `Missed the day ${r.eliminatedOnDay} deadline`
                : `Day ${r.eliminatedOnDay} proof rejected by the jury`}
          </p>
        </div>

        {r.survived && r.payout ? (
          <div className="border-3 border-bone/20 p-3">
            <p className="font-mono text-xs uppercase text-bone-muted">Your payout</p>
            <p className="font-mono text-3xl font-bold tabular-nums text-acid">+{sol(r.payout.amountLamports)} SOL</p>
            <div className="mt-1 flex flex-wrap items-center justify-between gap-2">
              <span className={`font-mono text-xs font-bold uppercase ${PAYOUT_STATE[r.payout.status].tone}`}>
                {PAYOUT_STATE[r.payout.status].label}
              </span>
              {r.payout.txSig && <TxLink sig={r.payout.txSig} label="View transaction" />}
            </div>
          </div>
        ) : r.survived ? (
          <p className="border-3 border-dashed border-bone/20 p-3 font-mono text-xs text-bone-muted">
            Final results are being tallied. Your payout appears here once the pool settles.
          </p>
        ) : (
          <p className="border-3 border-dashed border-fatal/40 p-3 font-mono text-xs text-bone-muted">
            {s && s.survivorCount === 0
              ? `Nobody survived; your ${sol(r.stakeLamports, 2)} SOL stake went to the platform.`
              : `Your ${sol(r.stakeLamports, 2)} SOL stake went to ${s ? `the ${s.survivorCount}` : "the"} survivors.`}
          </p>
        )}

        {s && (
          <dl className="mt-auto grid grid-cols-3 gap-2 font-mono text-[11px] uppercase text-bone-muted">
            <div>
              <dt>Survivors</dt>
              <dd className="text-base font-bold tabular-nums text-bone">{s.survivorCount}</dd>
            </div>
            <div>
              <dt>Pool</dt>
              <dd className="text-base font-bold tabular-nums text-bone">{sol(s.totalLamports, 2)}</dd>
            </div>
            <div>
              <dt>Each</dt>
              <dd className="text-base font-bold tabular-nums text-bone">{sol(s.payoutPerSurvivorLamports)}</dd>
            </div>
          </dl>
        )}
      </div>
    </article>
  );
}

export function ResultsSection({ results }: { results: PoolResult[] }) {
  if (results.length === 0) return null;
  return (
    <section aria-labelledby="results-heading">
      <h2 id="results-heading" className="font-display text-2xl font-bold uppercase text-bone">
        Results
      </h2>
      <p className="mb-4 font-mono text-xs text-bone-muted">
        Finished pools. Survivors split 90% of the pool; 10% goes to the platform.
      </p>
      <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
        {results.map((r) => (
          <ResultCard key={r.poolId} r={r} />
        ))}
      </div>
    </section>
  );
}

const REASON: Record<RefundView["reason"], string> = {
  WRONG_AMOUNT: "Wrong amount",
  UNMATCHED: "No pool open",
  DUPLICATE_ENTRY: "Already enrolled",
};

const REFUND_STATE: Record<RefundView["status"], { label: string; tone: string }> = {
  QUEUED: { label: "Queued", tone: "border-bone/40 text-bone-muted" },
  SENDING: { label: "Sending", tone: "border-cyan text-cyan" },
  REFUNDED: { label: "Refunded", tone: "border-acid text-acid" },
  UNDER_REVIEW: { label: "Under review", tone: "border-fatal text-fatal" },
};

export function RefundsSection({ refunds }: { refunds: RefundView[] }) {
  if (refunds.length === 0) return null;
  return (
    <section aria-labelledby="refunds-heading">
      <h2 id="refunds-heading" className="font-display text-2xl font-bold uppercase text-bone">
        Refunds
      </h2>
      <p className="mb-4 font-mono text-xs text-bone-muted">
        Deposits that couldn&apos;t enter a pool are returned in full.
      </p>
      <ul className="divide-y-3 divide-bone/10 border-3 border-bone/90 bg-obsidian-900">
        {refunds.map((r) => (
          <li key={r.depositTxSig} className="flex items-center justify-between gap-4 p-4">
            <div className="min-w-0">
              <p className="font-mono text-lg font-bold tabular-nums text-bone">{sol(r.amountLamports)} SOL</p>
              <p className="font-mono text-[11px] uppercase text-bone-muted">
                {REASON[r.reason]} · {new Date(r.receivedAt).toISOString().slice(0, 16).replace("T", " ")} UTC
              </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
              <span className={`border-2 px-2 py-0.5 font-mono text-[11px] font-bold uppercase ${REFUND_STATE[r.status].tone}`}>
                {REFUND_STATE[r.status].label}
              </span>
              {r.refundTxSig && <TxLink sig={r.refundTxSig} label="Tx" />}
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
