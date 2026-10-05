import puppeteer, { type Browser, type LaunchOptions, type Page } from "puppeteer";

export type Layout = "portrait" | "landscape";

export const SIZES: Record<Layout, { width: number; height: number }> = {
  portrait: { width: 1080, height: 1920 },
  landscape: { width: 1920, height: 1080 },
};

/** Flags that keep Chromium rendering at full speed when nobody is looking at it. */
export const RENDER_FLAGS = [
  "--no-first-run",
  "--no-default-browser-check",
  "--disable-background-timer-throttling",
  "--disable-backgrounding-occluded-windows",
  "--disable-renderer-backgrounding",
  "--disable-features=Translate,MediaRouter,CalculateNativeWinOcclusion",
  "--disable-infobars",
  "--disable-session-crashed-bubble",
  "--hide-scrollbars",
  "--mute-audio",
  "--autoplay-policy=no-user-gesture-required",
  "--force-device-scale-factor=1",
  "--force-color-profile=srgb",
  "--enable-gpu-rasterization",
  "--ignore-gpu-blocklist",
];

export function overlayUrl(base: string, layout: Layout): string {
  const url = new URL(base);
  url.searchParams.set("layout", layout);
  url.searchParams.set("capture", "1");
  return url.toString();
}

export async function launch(layout: Layout, opts: { headless: boolean; kiosk?: boolean }): Promise<Browser> {
  const { width, height } = SIZES[layout];
  const args = [...RENDER_FLAGS, `--window-size=${width},${height}`, "--window-position=0,0"];
  if (process.getuid?.() === 0 || process.env.CHROME_NO_SANDBOX === "1") args.push("--no-sandbox", "--disable-dev-shm-usage");
  if (opts.kiosk) args.push("--kiosk", "--start-fullscreen", "--use-gl=angle", "--use-angle=swiftshader");
  const options: LaunchOptions = {
    headless: opts.headless,
    args,
    defaultViewport: opts.kiosk ? null : { width, height, deviceScaleFactor: 1 },
    ignoreDefaultArgs: ["--enable-automation"],
    executablePath: process.env.CHROME_PATH || undefined,
    protocolTimeout: 60_000,
  };
  return puppeteer.launch(options);
}

export async function openOverlay(browser: Browser, url: string, layout: Layout): Promise<Page> {
  const [existing] = await browser.pages();
  const page = existing ?? (await browser.newPage());
  const { width, height } = SIZES[layout];
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  page.on("pageerror", (err) => log("page error:", err instanceof Error ? err.message : String(err)));
  page.on("console", (msg) => {
    if (msg.type() === "error") log("console:", msg.text());
  });
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60_000 });
  await waitReady(page);
  return page;
}

export async function waitReady(page: Page, timeout = 60_000): Promise<void> {
  await page.waitForFunction(() => window.__OVERLAY_READY__ === true, { timeout, polling: 250 });
  // Let fonts, chart layout and first animations settle before frames go out.
  await page.evaluate(() => document.fonts.ready);
  await new Promise((r) => setTimeout(r, 750));
}

/** Seconds since the overlay last received data (tick/sync); Infinity if the page is unresponsive. */
export async function heartbeatAge(page: Page): Promise<number> {
  try {
    const hb = await page.evaluate(() => window.__OVERLAY_HEARTBEAT__ ?? 0);
    return hb ? (Date.now() - hb) / 1000 : Infinity;
  } catch {
    return Infinity;
  }
}

export function log(...args: unknown[]): void {
  console.log(`[${new Date().toISOString()}]`, ...args);
}

declare global {
  interface Window {
    __OVERLAY_READY__?: boolean;
    __OVERLAY_HEARTBEAT__?: number;
  }
}
