/**
 * Frontend smoke test for the Telegram Mini App and the stream overlay, driven by a real
 * Chromium against the running stack (server :4000, webapp :3000, overlay :5174).
 *
 *   pnpm test:ui                     # ALLOW_DEV_AUTH=true must be set for the server
 *   UI_SMOKE_OUT=./shots pnpm test:ui
 *
 * Mini App: live connection, chart canvas + streaming price, join → LONG → live PnL →
 * close, Top-10, deposit intent + signed webhook pushed back into the UI.
 * Overlay: portrait + landscape stage size, ready/heartbeat flags, chart, QR, Top-10 rows.
 */
import { createHmac, randomBytes } from "node:crypto";
import { existsSync, mkdirSync } from "node:fs";
import { resolve } from "node:path";
import puppeteer from "puppeteer";

const root = resolve(import.meta.dirname, "..");
if (existsSync(resolve(root, ".env"))) process.loadEnvFile(resolve(root, ".env"));

const WEBAPP = process.env.UI_WEBAPP_URL ?? "http://localhost:3000";
const OVERLAY = process.env.UI_OVERLAY_URL ?? "http://localhost:5174";
const SERVER = process.env.UI_SERVER_URL ?? `http://localhost:${process.env.SERVER_PORT ?? 4000}`;
const OUT = resolve(process.env.UI_SMOKE_OUT ?? resolve(root, "ui-smoke-output"));
const ROUND_WAIT_MS = Number(process.env.UI_ROUND_WAIT_MS ?? 90_000);
mkdirSync(OUT, { recursive: true });

let failures = 0;
const ok = (msg) => console.log(`  ✓ ${msg}`);
const fail = (msg) => {
  failures++;
  console.log(`  ✗ ${msg}`);
};
const check = (cond, msg) => (cond ? ok(msg) : fail(msg));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function waitFor(page, fn, timeout, ...args) {
  try {
    await page.waitForFunction(fn, { timeout, polling: 200 }, ...args);
    return true;
  } catch {
    return false;
  }
}
const text = (page, sel) => page.$eval(sel, (el) => el.textContent?.trim() ?? "").catch(() => null);
const click = (page, sel) => page.$eval(sel, (el) => el.click());

async function signedDeposit(reference, network, amount) {
  const secret = process.env.DEPOSIT_WEBHOOK_SECRET;
  if (!secret) throw new Error("DEPOSIT_WEBHOOK_SECRET missing");
  const body = JSON.stringify({ network, amount, memo: reference, txHash: randomBytes(32).toString("hex") });
  const ts = String(Math.floor(Date.now() / 1000));
  const sig = createHmac("sha256", secret).update(`${ts}.${body}`).digest("hex");
  const res = await fetch(`${SERVER}/api/webhooks/deposits`, {
    method: "POST",
    headers: { "content-type": "application/json", "x-timestamp": ts, "x-signature": sig },
    body,
  });
  return res.json();
}

async function testMiniApp(browser) {
  console.log("\nMini App", WEBAPP);
  const ctx = await browser.createBrowserContext(); // fresh storage → fresh dev user
  const page = await ctx.newPage();
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));

  await page.goto(WEBAPP, { waitUntil: "domcontentloaded", timeout: 60_000 });
  check(await waitFor(page, () => document.querySelector('[data-testid="conn"]')?.dataset.state === "online", 20_000), "socket connected (dev auth)");
  check(await waitFor(page, () => !!document.querySelector('[data-testid="chart"] canvas'), 15_000), "chart canvas rendered");

  const p1 = await text(page, '[data-testid="price"]');
  await sleep(2_500);
  const p2 = await text(page, '[data-testid="price"]');
  check(p1 && p2 && p1 !== "—" && p1 !== p2, `live price streaming (${p1} → ${p2})`);
  await page.screenshot({ path: `${OUT}/miniapp-1-join.png` });

  check(await waitFor(page, () => !!document.querySelector('[data-testid="join"]'), 10_000), "join card shown");
  const ticketsBefore = Number(await page.$eval('[data-testid="tickets"]', (el) => el.dataset.value));
  await click(page, '[data-testid="join"]');
  check(await waitFor(page, () => !!document.querySelector('[data-testid="account-strip"]'), 10_000), "joined round → account strip");
  const ticketsAfterJoin = Number(await page.$eval('[data-testid="tickets"]', (el) => el.dataset.value));
  check(ticketsAfterJoin === ticketsBefore - 1, `entry ticket charged (${ticketsBefore} → ${ticketsAfterJoin})`);

  console.log(`  … waiting up to ${ROUND_WAIT_MS / 1000}s for the round to go LIVE`);
  const live = await waitFor(page, () => {
    const b = document.querySelector('[data-testid="long"]');
    return b && !b.disabled;
  }, ROUND_WAIT_MS);
  check(live, "round LIVE → order buttons enabled");
  if (live) {
    await page.screenshot({ path: `${OUT}/miniapp-2-order.png` });
    await click(page, '[data-testid="long"]');
    check(await waitFor(page, () => !!document.querySelector('[data-testid="position"]'), 8_000), "LONG opened → position card");
    const pnl1 = await text(page, '[data-testid="position-pnl"]');
    await waitFor(page, (v) => document.querySelector('[data-testid="position-pnl"]')?.textContent?.trim() !== v, 6_000, pnl1);
    const pnl2 = await text(page, '[data-testid="position-pnl"]');
    check(pnl1 !== pnl2, `unrealized PnL updates with ticks (${pnl1} → ${pnl2})`);
    await page.screenshot({ path: `${OUT}/miniapp-3-position.png` });

    await click(page, '[data-testid="close-position"]');
    check(await waitFor(page, () => !document.querySelector('[data-testid="position"]'), 8_000), "position closed");
    check(await waitFor(page, () => document.querySelectorAll('[data-testid="recent-trade"]').length > 0, 5_000), "closed trade listed");
  }

  await click(page, '[data-testid="tab-ranks"]');
  check(await waitFor(page, () => document.querySelectorAll('[data-testid="lb-row"]').length > 0, 8_000), "Top-10 leaderboard rendered");
  const rows = await page.$$eval('[data-testid="lb-row"]', (els) => els.length);
  console.log(`    ${rows} leaderboard rows`);
  await page.screenshot({ path: `${OUT}/miniapp-4-ranks.png` });

  await click(page, '[data-testid="tab-wallet"]');
  await waitFor(page, () => !!document.querySelector('[data-testid="create-deposit"]'), 5_000);
  await click(page, '[data-testid="create-deposit"]');
  check(await waitFor(page, () => /^TA-/.test(document.querySelector('[data-testid="deposit-card"]')?.dataset.reference ?? ""), 8_000), "deposit intent created");
  const reference = await page.$eval('[data-testid="deposit-card"]', (el) => el.dataset.reference);
  const amount = await page.evaluate(() => {
    const row = [...document.querySelectorAll('[data-testid="deposit-card"] button')].find((b) => b.textContent.includes("Amount"));
    return row?.querySelector(".font-mono")?.textContent?.trim();
  });
  const ticketsBeforeDeposit = Number(await page.$eval('[data-testid="tickets"]', (el) => el.dataset.value));
  const hook = await signedDeposit(reference, "TRC20", amount);
  check(hook.status === "credited", `signed webhook credited ${reference} (${amount} USDT)`);
  check(
    await waitFor(page, (n) => Number(document.querySelector('[data-testid="tickets"]')?.dataset.value) > n, 8_000, ticketsBeforeDeposit),
    "tickets pushed to UI over WebSocket (wallet:update)",
  );
  await waitFor(page, () => document.querySelector('[data-testid="deposit-card"]')?.dataset.status === "CONFIRMED", 5_000);
  await page.screenshot({ path: `${OUT}/miniapp-5-wallet.png`, fullPage: true });

  check(errors.length === 0, `no page errors${errors.length ? `: ${errors.join(" | ")}` : ""}`);
  await ctx.close();
}

async function testOverlay(browser, layout, width, height) {
  console.log(`\nOverlay ${layout} ${width}x${height}`);
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 0.5 });
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(`${OVERLAY}/?layout=${layout}&capture=1`, { waitUntil: "domcontentloaded", timeout: 60_000 });
  check(await waitFor(page, () => window.__OVERLAY_READY__ === true, 20_000), "spectator snapshot loaded (__OVERLAY_READY__)");
  const stage = await page.$eval(".stage", (el) => ({ w: el.offsetWidth, h: el.offsetHeight }));
  check(stage.w === width && stage.h === height, `stage is ${stage.w}x${stage.h}`);
  const hb1 = await page.evaluate(() => window.__OVERLAY_HEARTBEAT__);
  await sleep(1_500);
  const hb2 = await page.evaluate(() => window.__OVERLAY_HEARTBEAT__);
  check(hb2 > hb1, "heartbeat advancing (live ticks)");
  check(await waitFor(page, () => !!document.querySelector(".chart canvas"), 5_000), "live chart canvas");
  check(!!(await page.$(".qr svg")), "QR code rendered");
  check(await waitFor(page, () => document.querySelectorAll(".board__row").length > 0, 10_000), "Top-10 rows rendered");
  await sleep(1_000); // let rank-slide animations settle
  await page.screenshot({ path: `${OUT}/overlay-${layout}.png` });
  check(errors.length === 0, `no page errors${errors.length ? `: ${errors.join(" | ")}` : ""}`);
  await page.close();
}

const health = await fetch(`${SERVER}/health/ready`).then((r) => r.json()).catch(() => null);
if (!health?.ok) {
  console.error(`Server not ready at ${SERVER} — start Redis and \`pnpm dev:server\` first.`);
  process.exit(1);
}

const browser = await puppeteer.launch({
  headless: true,
  args: process.getuid?.() === 0 ? ["--no-sandbox", "--disable-dev-shm-usage"] : [],
  executablePath: process.env.CHROME_PATH || undefined,
});
try {
  await testMiniApp(browser);
  await testOverlay(browser, "portrait", 1080, 1920);
  await testOverlay(browser, "landscape", 1920, 1080);
} finally {
  await browser.close();
}
console.log(`\nScreenshots: ${OUT}`);
console.log(failures ? `\n${failures} check(s) FAILED` : "\nALL UI CHECKS PASSED");
process.exit(failures ? 1 : 0);
