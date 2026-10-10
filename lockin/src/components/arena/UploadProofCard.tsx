"use client";

import { useEffect, useRef, useState } from "react";
import { uploadProofVideo, type ProofUploadHandle } from "@/lib/tus-upload";
import { MAX_VIDEO_BYTES, MAX_VIDEO_SECONDS } from "@/lib/video";
import { useSignedPost, useWalletSigner } from "./hooks";
import { ProofRecorder } from "./ProofRecorder";
import { errorMessage } from "@/lib/errors";

type Phase = "pick" | "ready" | "authorizing" | "uploading" | "submitting" | "done";

/**
 * Reads a video's duration and display size in the browser. Returns null when the
 * browser can't decode it (e.g. HEVC on some desktops); Cloudflare still enforces
 * the duration limit in that case.
 */
function probeVideo(file: File): Promise<{ width: number; height: number; duration: number } | null> {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const v = document.createElement("video");
    const finish = (r: { width: number; height: number; duration: number } | null) => {
      URL.revokeObjectURL(url);
      resolve(r);
    };
    v.preload = "metadata";
    v.muted = true;
    v.onloadedmetadata = () => {
      // MediaRecorder WebM files report Infinity until seeked to the end.
      if (v.duration === Infinity) {
        v.currentTime = 1e9;
        v.ontimeupdate = () => {
          v.ontimeupdate = null;
          finish({ width: v.videoWidth, height: v.videoHeight, duration: v.duration });
        };
      } else {
        finish({ width: v.videoWidth, height: v.videoHeight, duration: v.duration });
      }
    };
    v.onerror = () => finish(null);
    setTimeout(() => finish(null), 8000);
    v.src = url;
  });
}

async function validate(file: File): Promise<string | null> {
  if (!file.type.startsWith("video/")) return "That file isn't a video.";
  if (file.size > MAX_VIDEO_BYTES) return `Video is over ${MAX_VIDEO_BYTES / 1024 / 1024} MB.`;
  const meta = await probeVideo(file);
  if (!meta) return null;
  if (meta.width > meta.height) return "Proofs must be vertical (portrait). Record holding your phone upright.";
  if (Number.isFinite(meta.duration) && meta.duration > MAX_VIDEO_SECONDS + 0.5) {
    return `Proofs can be at most ${MAX_VIDEO_SECONDS / 60} minutes (this one is ${Math.round(meta.duration)}s).`;
  }
  return null;
}

const PHASE_LABEL: Record<Phase, string> = {
  pick: "Upload & submit",
  ready: "Upload & submit",
  authorizing: "Sign in wallet…",
  uploading: "Uploading…",
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
  const sign = useWalletSigner();
  const signedPost = useSignedPost();
  const inputRef = useRef<HTMLInputElement>(null);
  const uploadRef = useRef<ProofUploadHandle | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [phase, setPhase] = useState<Phase>("pick");
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const busy = phase === "authorizing" || phase === "uploading" || phase === "submitting";

  useEffect(() => {
    if (!file) return setPreviewUrl(null);
    const url = URL.createObjectURL(file);
    setPreviewUrl(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  useEffect(() => () => uploadRef.current?.abort(), []);

  async function choose(f: File | null) {
    setError(null);
    if (!f) return;
    const problem = await validate(f);
    if (problem) {
      setFile(null);
      setPhase("pick");
      setError(problem);
      return;
    }
    setFile(f);
    setPhase("ready");
  }

  async function submit() {
    if (!file) return;
    setError(null);
    try {
      setPhase("authorizing");
      const auth = await sign("upload-video", { poolId, dayNumber });

      setPhase("uploading");
      setProgress(0);
      const handle = uploadProofVideo({
        file,
        poolId,
        dayNumber,
        auth,
        onProgress: (sent, total) => setProgress(total ? sent / total : 0),
      });
      uploadRef.current = handle;
      const uid = await handle.done;
      uploadRef.current = null;

      setPhase("submitting");
      await signedPost("/api/proofs", "submit-proof", { poolId, dayNumber, videoCfId: uid });

      setPhase("done");
      onSubmitted();
    } catch (e) {
      uploadRef.current = null;
      setPhase(file ? "ready" : "pick");
      setError(errorMessage(e, "Submission failed."));
    }
  }

  function cancelUpload() {
    uploadRef.current?.abort();
    uploadRef.current = null;
    setPhase("ready");
    setError("Upload paused. Press upload again to resume where it stopped.");
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
          Vertical video, up to {MAX_VIDEO_SECONDS / 60} minutes. Your peers vote it legit or fraud. Miss the
          deadline and you&apos;re out.
        </p>
      </div>

      {previewUrl ? (
        <div className="flex flex-col gap-2">
          <video
            src={previewUrl}
            controls
            playsInline
            className="mx-auto aspect-[9/16] w-full max-w-[240px] border-3 border-bone/40 bg-obsidian object-contain"
          />
          {!busy && phase !== "done" && (
            <button
              type="button"
              onClick={() => {
                setFile(null);
                setPhase("pick");
              }}
              className="font-mono text-xs uppercase text-bone-muted underline underline-offset-4 hover:text-bone"
            >
              Discard and redo
            </button>
          )}
        </div>
      ) : (
        <>
          <ProofRecorder disabled={busy} onRecorded={choose} />
          <input
            ref={inputRef}
            type="file"
            accept="video/*"
            capture="user"
            className="sr-only"
            aria-label="Choose proof video"
            onChange={(e) => {
              void choose(e.target.files?.[0] ?? null);
              e.target.value = "";
            }}
          />
          <button
            type="button"
            disabled={busy}
            onClick={() => inputRef.current?.click()}
            className="border-3 border-bone/60 bg-obsidian px-4 py-3 text-left font-mono text-sm text-bone hover:border-cyan disabled:opacity-40"
          >
            + Upload a video file
          </button>
        </>
      )}

      {phase === "uploading" && (
        <div>
          <div
            className="h-3 w-full border-2 border-cyan"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={Math.round(progress * 100)}
          >
            <div className="h-full bg-cyan transition-[width]" style={{ width: `${progress * 100}%` }} />
          </div>
          <div className="mt-1 flex justify-between font-mono text-xs text-bone-muted">
            <span>{Math.round(progress * 100)}% uploaded</span>
            <button type="button" onClick={cancelUpload} className="uppercase underline underline-offset-4 hover:text-bone">
              Pause
            </button>
          </div>
        </div>
      )}

      <button
        type="button"
        disabled={!file || busy || phase === "done"}
        onClick={submit}
        className="border-3 border-cyan bg-cyan px-4 py-3 font-mono text-sm font-bold uppercase text-obsidian transition hover:translate-x-[2px] hover:translate-y-[2px] disabled:cursor-not-allowed disabled:opacity-40"
      >
        {PHASE_LABEL[phase]}
      </button>
      {error && (
        <p role="alert" className="font-mono text-xs text-fatal">
          {error}
        </p>
      )}
    </section>
  );
}
