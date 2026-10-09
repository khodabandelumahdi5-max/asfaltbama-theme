"use client";

import { useRef, useState } from "react";
import { useSignedPost } from "./hooks";

type Phase = "idle" | "authorizing" | "uploading" | "submitting" | "done";

const PHASE_LABEL: Record<Phase, string> = {
  idle: "Upload proof",
  authorizing: "Sign in wallet…",
  uploading: "Uploading video…",
  submitting: "Sign submission…",
  done: "Submitted",
};

export function UploadProofCard({
  poolId,
  dayNumber,
  deadline,
  onSubmitted,
}: {
  poolId: string;
  dayNumber: number;
  deadline: Date;
  onSubmitted: () => void;
}) {
  const signedPost = useSignedPost();
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [phase, setPhase] = useState<Phase>("idle");
  const [error, setError] = useState<string | null>(null);
  const busy = phase !== "idle" && phase !== "done";

  async function submit() {
    if (!file) return;
    setError(null);
    try {
      setPhase("authorizing");
      const { uploadURL, uid } = await signedPost<{ uploadURL: string; uid: string }>(
        "/api/proofs/upload-url",
        "upload-url",
        { poolId },
      );

      setPhase("uploading");
      const form = new FormData();
      form.append("file", file);
      const up = await fetch(uploadURL, { method: "POST", body: form });
      if (!up.ok) throw new Error(`video upload failed (${up.status})`);

      setPhase("submitting");
      await signedPost("/api/proofs", "submit-proof", { poolId, dayNumber, videoCfId: uid });

      setPhase("done");
      onSubmitted();
    } catch (e) {
      setPhase("idle");
      setError(e instanceof Error ? e.message : "submission failed");
    }
  }

  return (
    <section className="flex flex-col gap-4 border-3 border-dashed border-cyan bg-obsidian-900 p-5 shadow-brutal-cyan">
      <div>
        <p className="font-mono text-xs uppercase tracking-[0.3em] text-cyan">
          Due {deadline.toISOString().slice(0, 16).replace("T", " ")} UTC
        </p>
        <h3 className="font-display text-2xl font-bold uppercase text-bone">
          Day {String(dayNumber).padStart(2, "0")} proof
        </h3>
        <p className="mt-1 text-sm text-bone-muted">
          Record up to 2 minutes. Your peers vote it legit or fraud. Miss the deadline and you&apos;re out.
        </p>
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="video/*"
        capture="user"
        className="sr-only"
        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
      />
      <button
        type="button"
        disabled={busy}
        onClick={() => inputRef.current?.click()}
        className="border-3 border-bone/60 bg-obsidian px-4 py-3 text-left font-mono text-sm text-bone hover:border-cyan disabled:opacity-40"
      >
        {file ? `▣ ${file.name}` : "+ Choose or record video"}
      </button>

      <button
        type="button"
        disabled={!file || busy || phase === "done"}
        onClick={submit}
        className="border-3 border-cyan bg-cyan px-4 py-3 font-mono text-sm font-bold uppercase text-obsidian transition hover:translate-x-[2px] hover:translate-y-[2px] disabled:cursor-not-allowed disabled:opacity-40"
      >
        {PHASE_LABEL[phase]}
      </button>
      {error && <p role="alert" className="font-mono text-xs text-fatal">{error}</p>}
    </section>
  );
}
