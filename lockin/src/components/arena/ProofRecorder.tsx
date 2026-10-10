"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { MAX_VIDEO_SECONDS } from "@/lib/video";

/** Output frame for in-browser recordings: vertical 9:16. */
const OUT_W = 720;
const OUT_H = 1280;
const FPS = 30;
const VIDEO_BITRATE = 2_500_000;

const MIME_CANDIDATES = [
  "video/mp4;codecs=avc1.42E01E,mp4a.40.2",
  "video/mp4",
  "video/webm;codecs=vp9,opus",
  "video/webm;codecs=vp8,opus",
  "video/webm",
];

function pickMimeType(): string | undefined {
  if (typeof MediaRecorder === "undefined") return undefined;
  return MIME_CANDIDATES.find((t) => MediaRecorder.isTypeSupported(t));
}

type Phase = "idle" | "starting" | "live" | "recording";

/**
 * Records a vertical proof video from the camera. When the camera delivers
 * portrait frames (phones) they are recorded as-is; landscape frames (laptops)
 * are centre-cropped to 9:16 through a canvas, so every proof is vertical.
 */
export function ProofRecorder({
  disabled,
  onRecorded,
}: {
  disabled?: boolean;
  onRecorded: (file: File) => void;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const recorderRef = useRef<MediaRecorder | null>(null);
  const stopDrawRef = useRef<(() => void) | null>(null);
  const tickRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const [phase, setPhase] = useState<Phase>("idle");
  const [elapsed, setElapsed] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const supported =
    typeof navigator !== "undefined" && !!navigator.mediaDevices?.getUserMedia && pickMimeType() !== undefined;

  const releaseCamera = useCallback(() => {
    stopDrawRef.current?.();
    stopDrawRef.current = null;
    if (tickRef.current) clearInterval(tickRef.current);
    tickRef.current = null;
    streamRef.current?.getTracks().forEach((t) => t.stop());
    streamRef.current = null;
    if (videoRef.current) videoRef.current.srcObject = null;
  }, []);

  useEffect(() => releaseCamera, [releaseCamera]);

  async function startCamera() {
    setError(null);
    setPhase("starting");
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "user", width: { ideal: 1080 }, height: { ideal: 1920 }, aspectRatio: { ideal: 9 / 16 } },
        audio: true,
      });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play().catch(() => {});
      }
      setPhase("live");
    } catch (e) {
      const name = (e as DOMException)?.name;
      setError(
        name === "NotAllowedError"
          ? "Camera access was blocked. Allow it in your browser, or upload a video file instead."
          : name === "NotFoundError"
            ? "No camera found. Upload a video file instead."
            : "Couldn't start the camera. Upload a video file instead.",
      );
      releaseCamera();
      setPhase("idle");
    }
  }

  /** Returns a stream whose video is 9:16, cropping on a canvas if the camera is landscape. */
  function verticalStream(camera: MediaStream): MediaStream {
    const track = camera.getVideoTracks()[0];
    const { width = 0, height = 0 } = track.getSettings();
    if (height >= width) return camera;

    const video = videoRef.current!;
    const canvas = document.createElement("canvas");
    canvas.width = OUT_W;
    canvas.height = OUT_H;
    const ctx = canvas.getContext("2d")!;
    let running = true;
    const draw = () => {
      if (!running) return;
      const vw = video.videoWidth || width;
      const vh = video.videoHeight || height;
      const sw = Math.min(vw, (vh * 9) / 16);
      ctx.drawImage(video, (vw - sw) / 2, 0, sw, vh, 0, 0, OUT_W, OUT_H);
    };
    // setInterval keeps drawing when the tab is in the background (rAF would pause).
    const id = setInterval(draw, 1000 / FPS);
    stopDrawRef.current = () => {
      running = false;
      clearInterval(id);
    };
    draw();
    const out = canvas.captureStream(FPS);
    camera.getAudioTracks().forEach((t) => out.addTrack(t));
    return out;
  }

  function startRecording() {
    const camera = streamRef.current;
    const mimeType = pickMimeType();
    if (!camera || !mimeType) return;

    const chunks: Blob[] = [];
    const recorder = new MediaRecorder(verticalStream(camera), { mimeType, videoBitsPerSecond: VIDEO_BITRATE });
    recorderRef.current = recorder;
    recorder.ondataavailable = (e) => {
      if (e.data.size > 0) chunks.push(e.data);
    };
    recorder.onstop = () => {
      releaseCamera();
      setPhase("idle");
      const type = mimeType.split(";")[0];
      const ext = type === "video/mp4" ? "mp4" : "webm";
      onRecorded(new File(chunks, `proof-${Date.now()}.${ext}`, { type, lastModified: Date.now() }));
    };

    const started = Date.now();
    setElapsed(0);
    tickRef.current = setInterval(() => {
      const s = Math.floor((Date.now() - started) / 1000);
      setElapsed(s);
      if (s >= MAX_VIDEO_SECONDS && recorder.state === "recording") recorder.stop();
    }, 250);
    recorder.start(1000);
    setPhase("recording");
  }

  function stopRecording() {
    if (recorderRef.current?.state === "recording") recorderRef.current.stop();
  }

  function cancel() {
    if (recorderRef.current?.state === "recording") {
      recorderRef.current.onstop = null;
      recorderRef.current.stop();
    }
    releaseCamera();
    setPhase("idle");
  }

  if (!supported) {
    return (
      <p className="font-mono text-xs text-bone-muted">
        In-browser recording isn&apos;t available here. Upload a video file instead.
      </p>
    );
  }

  const remaining = MAX_VIDEO_SECONDS - elapsed;
  const showCamera = phase === "live" || phase === "recording" || phase === "starting";

  return (
    <div className="flex flex-col gap-3">
      <div
        className={`relative mx-auto aspect-[9/16] w-full max-w-[240px] overflow-hidden border-3 bg-obsidian ${
          phase === "recording" ? "border-fatal" : "border-bone/40"
        } ${showCamera ? "" : "hidden"}`}
      >
        {/* object-cover shows the same centre crop the recording will have */}
        <video ref={videoRef} muted playsInline className="h-full w-full -scale-x-100 object-cover" />
        {phase === "recording" && (
          <span className="absolute left-2 top-2 flex items-center gap-2 bg-obsidian/80 px-2 py-1 font-mono text-xs font-bold text-fatal">
            <span className="inline-block h-2 w-2 animate-flicker bg-fatal" />
            REC {String(Math.floor(remaining / 60)).padStart(1, "0")}:{String(remaining % 60).padStart(2, "0")}
          </span>
        )}
      </div>

      {phase === "idle" && (
        <button
          type="button"
          disabled={disabled}
          onClick={startCamera}
          className="border-3 border-bone/60 bg-obsidian px-4 py-3 text-left font-mono text-sm text-bone hover:border-cyan disabled:opacity-40"
        >
          ● Record with camera
        </button>
      )}
      {phase === "starting" && <p className="font-mono text-xs text-bone-muted">Starting camera…</p>}
      {phase === "live" && (
        <div className="grid grid-cols-[1fr_auto] gap-2">
          <button
            type="button"
            onClick={startRecording}
            className="border-3 border-fatal bg-fatal px-4 py-3 font-mono text-sm font-bold uppercase text-obsidian"
          >
            Start recording
          </button>
          <button type="button" onClick={cancel} className="border-3 border-bone/40 px-3 font-mono text-xs uppercase text-bone">
            Cancel
          </button>
        </div>
      )}
      {phase === "recording" && (
        <button
          type="button"
          onClick={stopRecording}
          className="border-3 border-bone bg-bone px-4 py-3 font-mono text-sm font-bold uppercase text-obsidian"
        >
          ■ Stop ({elapsed}s)
        </button>
      )}
      {error && (
        <p role="alert" className="font-mono text-xs text-fatal">
          {error}
        </p>
      )}
    </div>
  );
}
