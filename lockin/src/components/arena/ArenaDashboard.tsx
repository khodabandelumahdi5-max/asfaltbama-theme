"use client";

import { useWallet } from "@solana/wallet-adapter-react";
import { LAMPORTS_PER_SOL } from "@solana/web3.js";
import { useState, type ReactNode } from "react";
import { STAKE_SOL } from "@/lib/constants";
import type { ArenaState } from "@/lib/types";
import { useHasMounted } from "@/lib/use-has-mounted";
import { WalletButton } from "../wallet/WalletButton";
import { useArena, useSignedPost, useStakeDeposit } from "./hooks";
import { ProofSubmissionCard } from "./ProofSubmissionCard";
import { StreakTracker } from "./StreakTracker";
import { UploadProofCard } from "./UploadProofCard";

export function ArenaDashboard() {
  // Wallet state only exists in the browser. Until hydration finishes we render
  // exactly what the server rendered (the skeleton), so markup always matches.
  const mounted = useHasMounted();
  const { publicKey } = useWallet();
  const wallet = mounted && publicKey ? publicKey.toBase58() : null;

  if (!mounted) return <ArenaSkeleton />;
  if (!wallet) return <ConnectGate />;
  return <ConnectedArena key={wallet} wallet={wallet} />;
}

function ConnectedArena({ wallet }: { wallet: string }) {
  const { state, error, loading, refresh } = useArena(wallet);
  const signedPost = useSignedPost();

  if (!state) {
    return error ? <ErrorPanel message={error} onRetry={refresh} /> : <ArenaSkeleton />;
  }
  if (!state.pool) return <EmptyPanel title="No active pool" body="The next 21-day pool hasn't opened yet." />;
  if (!state.participant) return <JoinPanel wallet={wallet} state={state} onJoined={refresh} />;

  const { pool, participant, myProofs, reviewQueue } = state;
  const submitted = new Set(myProofs.map((p) => p.dayNumber));
  const submittedToday = submitted.has(pool.currentDay);
  // Yesterday (while its grace period runs) comes before today.
  const dueDay = participant.isEliminated
    ? null
    : ([pool.graceDay, pool.currentDay].find((d) => d !== null && d >= 1 && d <= 21 && !submitted.has(d)) ?? null);

  async function vote(submissionId: string, v: 1 | -1) {
    await signedPost("/api/reviews", "review", { submissionId, vote: v });
    await refresh();
  }

  return (
    <div className={`flex flex-col gap-8 ${loading ? "opacity-80" : ""}`}>
      <PoolStats state={state} />

      {participant.isEliminated && (
        <div role="status" className="border-3 border-fatal bg-fatal/10 p-5 shadow-brutal-fatal">
          <p className="font-display text-3xl font-black uppercase text-fatal">Eliminated</p>
          <p className="font-mono text-sm text-bone">
            {participant.eliminationReason === "MISSED_DEADLINE"
              ? `No proof for day ${participant.eliminatedOnDay} before the deadline.`
              : `Your day ${participant.eliminatedOnDay} proof was rejected by the jury.`}{" "}
            Your stake stays in the pool.
          </p>
        </div>
      )}

      <div className="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <StreakTracker
          currentDay={pool.currentDay}
          graceDay={pool.graceDay}
          eliminatedOnDay={participant.eliminatedOnDay}
          currentStreak={participant.currentStreak}
          proofs={myProofs}
        />
        {dueDay !== null ? (
          <UploadProofCard
            key={dueDay}
            poolId={pool.id}
            dayNumber={dueDay}
            deadline={dayDeadline(pool.startDate, dueDay, pool.graceHours)}
            onSubmitted={refresh}
          />
        ) : (
          <EmptyPanel
            title={submittedToday ? "Today's proof is in" : pool.currentDay < 1 ? "Pool starts soon" : "Nothing due"}
            body={
              submittedToday
                ? "Come back tomorrow. Meanwhile, review your rivals below."
                : pool.currentDay < 1
                  ? `Day 1 begins ${pool.startDate}.`
                  : "No proof can be filed right now."
            }
          />
        )}
      </div>

      <CardSection
        title="Jury duty"
        subtitle="Review pending proofs. Three net votes settle a verdict."
        empty="Queue clear. No pending proofs to review."
      >
        {reviewQueue.map((p) => (
          <ProofSubmissionCard key={p.id} proof={p} mode="review" onVote={vote} />
        ))}
      </CardSection>

      <CardSection title="Your proofs" subtitle="Every submission and its verdict." empty="No submissions yet.">
        {myProofs.map((p) => (
          <ProofSubmissionCard key={p.id} proof={p} mode="mine" />
        ))}
      </CardSection>
    </div>
  );
}

/** Day N closes at (start_date + N) 00:00 UTC plus the grace period. */
function dayDeadline(startDate: string, day: number, graceHours: number): Date {
  const [y, m, d] = startDate.split("-").map(Number);
  return new Date(Date.UTC(y, m - 1, d + day, graceHours));
}

function PoolStats({ state }: { state: ArenaState }) {
  const pool = state.pool!;
  const alive = pool.participantCount - pool.eliminatedCount;
  const locked = (pool.totalLockedLamports / LAMPORTS_PER_SOL).toFixed(2);
  const day = pool.currentDay < 1 ? "—" : Math.min(pool.currentDay, 21);

  const stats = [
    { label: "Day", value: `${day}/21`, tone: "text-cyan" },
    { label: "SOL locked", value: locked, tone: "text-acid" },
    { label: "Alive", value: alive, tone: "text-bone" },
    { label: "Eliminated", value: pool.eliminatedCount, tone: "text-fatal" },
  ];

  return (
    <section aria-label="Pool status">
      <p className="font-mono text-xs uppercase tracking-[0.3em] text-bone-muted">
        {pool.startDate} → {pool.endDate}
      </p>
      <h1 className="font-display text-4xl font-black uppercase leading-none text-bone sm:text-6xl">
        {pool.title}
      </h1>
      <dl className="mt-6 grid grid-cols-2 border-3 border-bone/90 sm:grid-cols-4">
        {stats.map((s, i) => (
          <div
            key={s.label}
            className={`p-4 ${i % 2 === 1 ? "border-l-3" : ""} ${i >= 2 ? "border-t-3 sm:border-t-0" : ""} ${i === 2 ? "sm:border-l-3" : ""} border-bone/90`}
          >
            <dt className="font-mono text-xs uppercase text-bone-muted">{s.label}</dt>
            <dd className={`font-mono text-3xl font-bold tabular-nums ${s.tone}`}>{s.value}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

function JoinPanel({
  wallet,
  state,
  onJoined,
}: {
  wallet: string;
  state: ArenaState;
  onJoined: () => void;
}) {
  const deposit = useStakeDeposit();
  const [phase, setPhase] = useState<"idle" | "sending" | "waiting">("idle");
  const [error, setError] = useState<string | null>(null);
  const pool = state.pool!;
  const enrollmentOpen = pool.currentDay <= 1;

  async function join() {
    setError(null);
    setPhase("sending");
    try {
      await deposit();
      setPhase("waiting");
      // The Helius webhook enrolls us; poll until the participant row exists.
      for (let i = 0; i < 20; i++) {
        await new Promise((r) => setTimeout(r, 3000));
        const res = await fetch(`/api/arena?wallet=${encodeURIComponent(wallet)}`, { cache: "no-store" });
        if (res.ok && ((await res.json()) as ArenaState).participant) {
          onJoined();
          return;
        }
      }
      throw new Error("Deposit confirmed on-chain but not yet indexed. Refresh in a minute.");
    } catch (e) {
      setError(e instanceof Error ? e.message : "deposit failed");
      setPhase("idle");
    }
  }

  return (
    <section className="flex flex-col gap-6">
      <PoolStats state={state} />
      <div className="border-3 border-acid bg-obsidian-900 p-6 shadow-brutal sm:p-8">
        <h2 className="font-display text-3xl font-black uppercase text-bone">Lock in or log off</h2>
        <p className="mt-2 max-w-prose text-bone-muted">
          Stake {STAKE_SOL} SOL. Post a video proof every day for 21 days. Your peers judge it. Miss a day or get
          voted out and your stake feeds the survivors.
        </p>
        <button
          type="button"
          disabled={!enrollmentOpen || phase !== "idle"}
          onClick={join}
          className="mt-6 border-3 border-acid bg-acid px-6 py-3 font-mono text-lg font-bold uppercase text-obsidian transition hover:translate-x-[3px] hover:translate-y-[3px] disabled:cursor-not-allowed disabled:opacity-40"
        >
          {!enrollmentOpen
            ? "Enrollment closed"
            : phase === "sending"
              ? "Confirm in wallet…"
              : phase === "waiting"
                ? "Verifying deposit…"
                : `Stake ${STAKE_SOL} SOL`}
        </button>
        {error && <p role="alert" className="mt-3 font-mono text-sm text-fatal">{error}</p>}
      </div>
    </section>
  );
}

function ConnectGate() {
  return (
    <section className="flex min-h-[60vh] flex-col items-start justify-center gap-6">
      <p className="font-mono text-xs uppercase tracking-[0.3em] text-acid">Solana devnet · 21-day protocol</p>
      <h1 className="font-display text-5xl font-black uppercase leading-[0.9] text-bone sm:text-7xl">
        Commit.
        <br />
        <span className="text-acid">Prove it.</span>
        <br />
        <span className="text-fatal">Or get cut.</span>
      </h1>
      <p className="max-w-prose text-bone-muted">
        Connect a wallet to enter the arena, track your streak and judge your rivals.
      </p>
      <WalletButton />
    </section>
  );
}

function CardSection({
  title,
  subtitle,
  empty,
  children,
}: {
  title: string;
  subtitle: string;
  empty: string;
  children: ReactNode[];
}) {
  return (
    <section>
      <h2 className="font-display text-2xl font-bold uppercase text-bone">{title}</h2>
      <p className="mb-4 font-mono text-xs text-bone-muted">{subtitle}</p>
      {children.length === 0 ? (
        <p className="border-3 border-dashed border-obsidian-600 p-6 font-mono text-sm text-bone-muted">{empty}</p>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">{children}</div>
      )}
    </section>
  );
}

function EmptyPanel({ title, body }: { title: string; body: string }) {
  return (
    <section className="flex flex-col justify-center border-3 border-obsidian-600 bg-obsidian-900 p-6">
      <h3 className="font-display text-xl font-bold uppercase text-bone">{title}</h3>
      <p className="mt-1 text-sm text-bone-muted">{body}</p>
    </section>
  );
}

function ErrorPanel({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <section role="alert" className="border-3 border-fatal p-6 shadow-brutal-fatal">
      <p className="font-mono text-sm text-fatal">{message}</p>
      <button
        type="button"
        onClick={onRetry}
        className="mt-4 border-3 border-bone px-4 py-2 font-mono text-sm uppercase text-bone hover:bg-bone hover:text-obsidian"
      >
        Retry
      </button>
    </section>
  );
}

function ArenaSkeleton() {
  return (
    <div className="flex flex-col gap-8" aria-busy="true" aria-label="Loading arena">
      <div className="h-16 w-2/3 animate-pulse bg-obsidian-800" />
      <div className="h-24 animate-pulse border-3 border-obsidian-700 bg-obsidian-900" />
      <div className="h-80 animate-pulse border-3 border-obsidian-700 bg-obsidian-900" />
    </div>
  );
}
