/**
 * Runs the overlay in a full-screen, non-headless Chromium on an X display (Xvfb) so
 * FFmpeg's x11grab can capture real composited frames at a steady 60fps. Supervises the
 * page: reloads when the data heartbeat goes stale, relaunches the browser if it dies.
 * Prints "OVERLAY_READY" once the first frame is fully rendered (stream script waits on it).
 *
 *   DISPLAY=:99 tsx capture/kiosk.ts --layout portrait
 */
import { parseArgs } from "node:util";
import type { Browser, Page } from "puppeteer";
import { heartbeatAge, launch, log, openOverlay, overlayUrl, waitReady, type Layout } from "./browser";

const { values } = parseArgs({
  options: {
    url: { type: "string", default: process.env.OVERLAY_URL ?? "http://localhost:5174" },
    layout: { type: "string", default: process.env.LAYOUT ?? "landscape" },
    "stale-after": { type: "string", default: "30" },
  },
});

if (!process.env.DISPLAY) {
  console.error("DISPLAY is not set — start Xvfb first (see scripts/stream_to_youtube.sh)");
  process.exit(1);
}

const layout = (values.layout === "portrait" ? "portrait" : "landscape") as Layout;
const url = overlayUrl(values.url!, layout);
const staleAfter = Number(values["stale-after"]);

let browser: Browser | null = null;
let page: Page | null = null;
let shuttingDown = false;
let announced = false;

async function start(): Promise<void> {
  browser = await launch(layout, { headless: false, kiosk: true });
  browser.on("disconnected", () => {
    if (shuttingDown) return;
    log("browser crashed/disconnected → relaunching in 2s");
    page = null;
    setTimeout(() => void start().catch(fatal), 2_000);
  });
  page = await openOverlay(browser, url, layout);
  log(`kiosk showing ${url}`);
  if (!announced) {
    announced = true;
    console.log("OVERLAY_READY");
  }
}

function fatal(err: unknown): void {
  log("fatal:", err);
  process.exit(1);
}

setInterval(async () => {
  if (!page) return;
  const age = await heartbeatAge(page);
  if (age > staleAfter) {
    log(`heartbeat stale (${age.toFixed(1)}s) → reloading overlay`);
    try {
      await page.reload({ waitUntil: "domcontentloaded", timeout: 30_000 });
      await waitReady(page);
    } catch (err) {
      log("reload failed, restarting browser", err);
      await browser?.close().catch(() => undefined);
    }
  }
}, 10_000);

const stop = async () => {
  shuttingDown = true;
  await browser?.close().catch(() => undefined);
  process.exit(0);
};
process.on("SIGINT", () => void stop());
process.on("SIGTERM", () => void stop());

start().catch(fatal);
