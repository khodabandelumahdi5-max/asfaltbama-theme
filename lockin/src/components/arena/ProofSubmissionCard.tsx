"use client";

import { useState } from "react";
import type { ProofCardData } from "@/lib/types";
import { errorMessage } from "@/lib/errors";

const STATUS_STYLE = {
  PENDING: { badge: "bg-cyan text-obsidian", frame: "shadow-brutal-cyan", label: "Pending" },
  VERIFIED: { badge: "bg-acid text-obsidian", frame: "shadow-brutal", label: "Verified" },
  REJECTED: { badge: "bg-fatal text-obsidian", frame: "shadow-brutal-fatal", label: "Rejected" },
} as const;

function shortWallet(w: string) {
  return `${w.slice(0, 4)}…${w.slice(-4)}`;
}

export function ProofSubmissionCard({
  proof,
  mode,
  onVote,
}: {
  proof: ProofCardData;
  /** "mine": your own submission. "review": someone else's, with vote controls. */
  mode: "mine" | "review";
  onVote?: (submissionId: string, vote: 1 | -1) => Promise<void>;
}) {
  const [playing, setPlaying] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const style = STATUS_STYLE[proof.status];
  const total = proof.approvals + proof.rejections;
  const approvalPct = total === 0 ? 0 : Math.round((proof.approvals / total) * 100);

  async function vote(v: 1 | -1) {
    if (!onVote) return;
    setBusy(true);
    setError(null);
    try {
      await onVote(proof.id, v);
    } catch (e) {
      setError(errorMessage(e, "Vote failed."));
    } finally {
      setBusy(false);
    }
  }

  return (
    <article className={`flex flex-col border-3 border-bone/90 bg-obsidian-900 ${style.frame}`}>
      <div className="relative aspect-video border-b-3 border-bone/90 bg-obsidian">
        {playing ? (
          <iframe
            src={`https://iframe.videodelivery.net/${proof.videoCfId}?autoplay=true`}
            title={`Day ${proof.dayNumber} proof video`}
            allow="autoplay; encrypted-media; picture-in-picture"
            allowFullScreen
            className="absolute inset-0 h-full w-full"
          />
        ) : (
          <button
            type="button"
            onClick={() => setPlaying(true)}
            className="group absolute inset-0 flex items-center justify-center"
            aria-label={`Play day ${proof.dayNumber} proof`}
          >
            {/* eslint-disable-next-line @next/next/no-img-element -- Cloudflare Stream thumbnail */}
            <img
              src={`https://videodelivery.net/${proof.videoCfId}/thumbnails/thumbnail.jpg?height=270`}
              alt=""
              loading="lazy"
              className="absolute inset-0 h-full w-full object-cover opacity-60 grayscale transition group-hover:opacity-90 group-hover:grayscale-0"
            />
            <span className="relative border-3 border-bone bg-obsidian px-4 py-2 font-mono text-sm font-bold uppercase text-bone group-hover:bg-acid group-hover:text-obsidian">
              ▶ Play
            </span>
          </button>
        )}
        <span className={`absolute left-0 top-0 px-2 py-1 font-mono text-xs font-bold uppercase ${style.badge}`}>
          {style.label}
        </span>
      </div>

      <div className="flex flex-1 flex-col gap-3 p-4">
        <div className="flex items-baseline justify-between font-mono">
          <h3 className="font-display text-xl font-bold uppercase text-bone">
            Day {String(proof.dayNumber).padStart(2, "0")}
          </h3>
          <span className="text-xs text-bone-muted">{shortWallet(proof.walletAddress)}</span>
        </div>

        <div>
          <div className="flex h-2 w-full border-2 border-obsidian-600" aria-hidden>
            {total === 0 ? (
              <div className="flex-1 bg-obsidian-700" />
            ) : (
              <>
                <div className="bg-acid" style={{ width: `${approvalPct}%` }} />
                <div className="flex-1 bg-fatal" />
              </>
            )}
          </div>
          <p className="mt-1 flex justify-between font-mono text-xs text-bone-muted">
            <span className="text-acid">+{proof.approvals} legit</span>
            <span className="text-fatal">−{proof.rejections} fraud</span>
          </p>
        </div>

        {mode === "review" && (
          <div className="mt-auto grid grid-cols-2 gap-2">
            <button
              type="button"
              disabled={busy}
              onClick={() => vote(1)}
              className="border-3 border-acid bg-obsidian py-2 font-mono text-sm font-bold uppercase text-acid transition hover:bg-acid hover:text-obsidian disabled:opacity-40"
            >
              Legit
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => vote(-1)}
              className="border-3 border-fatal bg-obsidian py-2 font-mono text-sm font-bold uppercase text-fatal transition hover:bg-fatal hover:text-obsidian disabled:opacity-40"
            >
              Fraud
            </button>
          </div>
        )}
        {error && <p role="alert" className="font-mono text-xs text-fatal">{error}</p>}
      </div>
    </article>
  );
}
