/**
 * Headless 1080p/60fps capture of the overlay → FFmpeg, without any display server.
 *
 * Chromium's DevTools screencast pushes JPEG frames only when pixels change, so a
 * constant-frame-rate pump re-emits the latest frame on a precise 1/fps schedule. That
 * yields a true CFR stream (required by YouTube/most encoders) at exactly --fps.
 *
 *   tsx capture/record.ts --layout portrait --duration 60 --out recordings/clip.mp4
 *   tsx capture/record.ts --layout landscape --out rtmp://a.rtmp.youtube.com/live2/<KEY>
 */
import { spawn } from "node:child_process";
import { mkdirSync } from "node:fs";
import { dirname } from "node:path";
import { parseArgs } from "node:util";
import { SIZES, heartbeatAge, launch, log, openOverlay, overlayUrl, waitReady, type Layout } from "./browser";

const { values } = parseArgs({
  options: {
    url: { type: "string", default: process.env.OVERLAY_URL ?? "http://localhost:5174" },
    layout: { type: "string", default: process.env.LAYOUT ?? "landscape" },
    fps: { type: "string", default: process.env.FPS ?? "60" },
    duration: { type: "string", default: process.env.DURATION ?? "0" },
    out: { type: "string", default: process.env.OUTPUT ?? "recordings/overlay.mp4" },
    bitrate: { type: "string", default: process.env.VIDEO_BITRATE ?? "8000k" },
    quality: { type: "string", default: "88" },
    encoder: { type: "string", default: process.env.VIDEO_ENCODER ?? "libx264" },
  },
});

const layout = (values.layout === "portrait" ? "portrait" : "landscape") as Layout;
const fps = Math.max(1, Math.min(120, Number(values.fps)));
const durationSec = Number(values.duration);
const out = values.out!;
const isLive = /^(rtmps?|srt|udp):\/\//.test(out);
const { width, height } = SIZES[layout];
const frameIntervalNs = BigInt(Math.round(1e9 / fps));

function ffmpegArgs(): string[] {
  const kbps = parseInt(values.bitrate!, 10);
  const args = [
    "-hide_banner", "-loglevel", "warning", "-stats",
    "-f", "image2pipe", "-c:v", "mjpeg", "-framerate", String(fps), "-thread_queue_size", "512", "-i", "pipe:0",
    "-f", "lavfi", "-i", "anullsrc=channel_layout=stereo:sample_rate=44100",
    "-map", "0:v", "-map", "1:a",
    "-vf", `scale=${width}:${height}:flags=bicubic,format=yuv420p`,
    "-c:v", values.encoder!,
  ];
  if (values.encoder === "libx264") args.push("-preset", isLive ? "veryfast" : "fast", "-tune", "zerolatency", "-profile:v", "high");
  args.push(
    "-b:v", `${kbps}k`, "-maxrate", `${kbps}k`, "-bufsize", `${kbps * 2}k`,
    "-r", String(fps), "-g", String(fps * 2), "-keyint_min", String(fps * 2), "-sc_threshold", "0",
    "-c:a", "aac", "-b:a", "128k", "-ar", "44100",
    "-shortest",
  );
  if (isLive) args.push("-f", out.startsWith("srt") ? "mpegts" : "flv", "-flvflags", "no_duration_filesize", out);
  else args.push("-movflags", "+faststart", "-y", out);
  return args;
}

async function main(): Promise<void> {
  if (!isLive) mkdirSync(dirname(out), { recursive: true });
  const browser = await launch(layout, { headless: true });
  const page = await openOverlay(browser, overlayUrl(values.url!, layout), layout);
  log(`overlay ready (${layout} ${width}x${height}) → ${isLive ? "live" : out} @ ${fps}fps`);

  const ffmpeg = spawn("ffmpeg", ffmpegArgs(), { stdio: ["pipe", "inherit", "inherit"] });
  let ffmpegAlive = true;
  ffmpeg.on("exit", (code) => {
    ffmpegAlive = false;
    log(`ffmpeg exited with code ${code}`);
  });
  ffmpeg.stdin.on("error", () => (ffmpegAlive = false));

  const cdp = await page.createCDPSession();
  let latest: Buffer | null = null;
  let received = 0;
  cdp.on("Page.screencastFrame", (frame: { data: string; sessionId: number }) => {
    latest = Buffer.from(frame.data, "base64");
    received++;
    cdp.send("Page.screencastFrameAck", { sessionId: frame.sessionId }).catch(() => undefined);
  });
  await cdp.send("Page.startScreencast", {
    format: "jpeg",
    quality: Number(values.quality),
    maxWidth: width,
    maxHeight: height,
    everyNthFrame: 1,
  });

  // Constant-frame-rate pump with drift-free hrtime scheduling. The clock starts at the
  // first real frame. Live outputs drop frames under encoder backpressure (latency matters);
  // file outputs never drop — Node buffers them and FFmpeg catches up.
  let written = 0;
  let dropped = 0;
  let backpressured = false;
  ffmpeg.stdin.on("drain", () => (backpressured = false));
  let start = 0n;
  let stopping = false;

  const pump = () => {
    if (stopping || !ffmpegAlive) return;
    if (!latest) {
      setTimeout(pump, 5);
      return;
    }
    if (start === 0n) start = process.hrtime.bigint();
    const now = process.hrtime.bigint();
    const due = Number((now - start) / frameIntervalNs) + 1;
    while (written + dropped < due) {
      if (isLive && backpressured) {
        dropped++;
      } else {
        backpressured = !ffmpeg.stdin.write(latest);
        written++;
      }
    }
    const next = start + BigInt(written + dropped) * frameIntervalNs;
    const waitMs = Number(next - process.hrtime.bigint()) / 1e6;
    setTimeout(pump, Math.max(0, waitMs - 1));
  };
  pump();

  const stats = setInterval(async () => {
    const age = await heartbeatAge(page);
    log(`frames written=${written} dropped=${dropped} screencast=${received} heartbeat=${age.toFixed(1)}s`);
    if (age > 30) {
      log("overlay stale → reloading");
      await page.reload({ waitUntil: "domcontentloaded" }).then(() => waitReady(page)).catch((e) => log("reload failed", e));
    }
  }, 5_000);

  const stop = async (reason: string) => {
    if (stopping) return;
    stopping = true;
    log(`stopping: ${reason}`);
    clearInterval(stats);
    await cdp.send("Page.stopScreencast").catch(() => undefined);
    ffmpeg.stdin.end();
    await new Promise((r) => (ffmpegAlive ? ffmpeg.once("exit", r) : r(null)));
    await browser.close().catch(() => undefined);
    process.exit(0);
  };

  if (durationSec > 0) {
    // Stop on the exact frame count, measured from the first captured frame.
    const target = Math.round(durationSec * fps);
    const check = setInterval(() => {
      if (written + dropped >= target) {
        clearInterval(check);
        void stop("duration reached");
      }
    }, 10);
  }
  process.on("SIGINT", () => void stop("SIGINT"));
  process.on("SIGTERM", () => void stop("SIGTERM"));
  ffmpeg.on("exit", () => void stop("ffmpeg exited"));
  browser.on("disconnected", () => {
    if (!stopping) {
      log("browser disconnected");
      process.exit(2);
    }
  });
}

main().catch((err) => {
  log("fatal:", err);
  process.exit(1);
});
