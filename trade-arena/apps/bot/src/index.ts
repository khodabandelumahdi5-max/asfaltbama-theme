import { Markup, Telegraf, type Context } from "telegraf";
import type { DepositNetwork } from "@arena/shared";
import { api, ApiError } from "./api";
import { config } from "./config";
import { depositInstructions, summary, welcome } from "./messages";
import { startNotifier } from "./notifier";

const bot = new Telegraf(config.TELEGRAM_BOT_TOKEN, { handlerTimeout: 15_000 });

const TICKET_PACKS = [1, 5, 10, 25] as const;

const openArenaButton = (label = "🚀 Open Arena") => Markup.button.webApp(label, config.WEBAPP_URL);

const mainKeyboard = Markup.inlineKeyboard([
  [openArenaButton()],
  [Markup.button.callback("💳 Buy tickets", "deposit"), Markup.button.callback("📊 My rank", "rank")],
  [Markup.button.callback("🎁 Invite friends", "invite")],
]);

/** Registers/refreshes the Telegram user on the server; returns the server profile. */
async function syncUser(ctx: Context, startPayload?: string) {
  const from = ctx.from;
  if (!from) throw new Error("No sender");
  return api.upsertUser({
    id: from.id,
    firstName: from.first_name,
    lastName: from.last_name,
    username: from.username,
    languageCode: from.language_code,
    isPremium: (from as { is_premium?: boolean }).is_premium ?? false,
    startPayload,
  });
}

function userError(err: unknown): string {
  if (err instanceof ApiError) return `⚠️ ${err.message}`;
  console.error("[bot] handler error", err);
  return "⚠️ Something went wrong, please try again.";
}

// Private chats only — group usage is not supported.
bot.use(async (ctx, next) => {
  if (ctx.chat && ctx.chat.type !== "private") return;
  return next();
});

/**
 * /start [payload] — deep-link entry. `t.me/<bot>?start=ref_<CODE>` attributes the referral
 * on first contact (the server ignores it for existing users and self-referrals).
 */
bot.start(async (ctx) => {
  const payload = ctx.payload?.trim() || undefined;
  try {
    const res = await syncUser(ctx, payload);
    await ctx.replyWithHTML(welcome(ctx.from.first_name, res.user.tickets, Boolean(payload?.startsWith("ref_"))), mainKeyboard);
  } catch (err) {
    await ctx.reply(userError(err));
  }
});

bot.command(["play", "app"], (ctx) => ctx.reply("Jump into the arena 👇", Markup.inlineKeyboard([[openArenaButton()]])));

async function sendRank(ctx: Context) {
  try {
    await syncUser(ctx);
    const s = await api.summary(ctx.from!.id);
    await ctx.replyWithHTML(summary(s), Markup.inlineKeyboard([[openArenaButton("⚡️ Trade now")]]));
  } catch (err) {
    await ctx.reply(userError(err));
  }
}
bot.command("rank", sendRank);
bot.action("rank", async (ctx) => {
  await ctx.answerCbQuery();
  await sendRank(ctx);
});

async function sendInvite(ctx: Context) {
  try {
    const res = await syncUser(ctx);
    const text = "Join me in Trade Arena — fast BTC trading tournaments ⚡️";
    const share = `https://t.me/share/url?url=${encodeURIComponent(res.referralLink)}&text=${encodeURIComponent(text)}`;
    await ctx.replyWithHTML(
      [
        "🎁 <b>Invite friends</b>",
        "You get a bonus 🎟 ticket when a friend makes their first deposit.",
        "",
        `Your link:\n<code>${res.referralLink}</code>`,
        `Friends joined: <b>${res.user.referralCount}</b>`,
      ].join("\n"),
      Markup.inlineKeyboard([[Markup.button.url("📤 Share", share)]]),
    );
  } catch (err) {
    await ctx.reply(userError(err));
  }
}
bot.command("invite", sendInvite);
bot.action("invite", async (ctx) => {
  await ctx.answerCbQuery();
  await sendInvite(ctx);
});

const networkKeyboard = Markup.inlineKeyboard([
  [Markup.button.callback("USDT · TRC20 (Tron)", "dep:TRC20"), Markup.button.callback("USDT · TON", "dep:TON")],
]);

bot.command("deposit", (ctx) => ctx.reply("Choose a network for your USDT deposit:", networkKeyboard));
bot.action("deposit", async (ctx) => {
  await ctx.answerCbQuery();
  await ctx.reply("Choose a network for your USDT deposit:", networkKeyboard);
});

bot.action(/^dep:(TRC20|TON)$/, async (ctx) => {
  await ctx.answerCbQuery();
  const network = ctx.match[1] as DepositNetwork;
  await ctx.editMessageText(
    `How many tickets? (1 🎟 = ${config.TICKET_PRICE_USDT} USDT)`,
    Markup.inlineKeyboard([
      TICKET_PACKS.map((n) => Markup.button.callback(`${n} 🎟`, `dep:${network}:${n}`)),
    ]),
  );
});

bot.action(/^dep:(TRC20|TON):(\d{1,4})$/, async (ctx) => {
  await ctx.answerCbQuery("Creating deposit…");
  const network = ctx.match[1] as DepositNetwork;
  const tickets = Number(ctx.match[2]);
  if (!TICKET_PACKS.includes(tickets as (typeof TICKET_PACKS)[number])) return;
  try {
    await syncUser(ctx);
    const deposit = await api.createDeposit(ctx.from!.id, network, tickets);
    await ctx.editMessageText(depositInstructions(deposit), { parse_mode: "HTML" });
  } catch (err) {
    await ctx.reply(userError(err));
  }
});

bot.help((ctx) =>
  ctx.replyWithHTML(
    [
      "<b>Commands</b>",
      "/play — open the trading arena",
      "/rank — your rank in the current round",
      "/deposit — buy tournament tickets with USDT",
      "/invite — your referral link",
    ].join("\n"),
  ),
);

bot.catch((err, ctx) => console.error(`[bot] update ${ctx.update.update_id} failed`, err));

async function main() {
  await bot.telegram.setMyCommands([
    { command: "play", description: "Open the trading arena" },
    { command: "rank", description: "Your rank in the current round" },
    { command: "deposit", description: "Buy tickets with USDT" },
    { command: "invite", description: "Invite friends, earn tickets" },
    { command: "help", description: "Help" },
  ]);
  await bot.telegram.setChatMenuButton({
    menuButton: { type: "web_app", text: "Trade", web_app: { url: config.WEBAPP_URL } },
  });

  const stopNotifier = startNotifier(bot.telegram);

  if (config.BOT_MODE === "webhook") {
    await bot.launch({
      webhook: {
        domain: config.BOT_WEBHOOK_DOMAIN!,
        port: config.BOT_WEBHOOK_PORT,
        path: "/telegram/webhook",
        secretToken: config.BOT_WEBHOOK_SECRET || undefined,
      },
      allowedUpdates: ["message", "callback_query"],
    });
  } else {
    // launch() resolves only when polling stops, so don't await it.
    void bot.launch({ dropPendingUpdates: false, allowedUpdates: ["message", "callback_query"] }).catch((err) => {
      console.error("[bot] polling stopped", err);
      process.exit(1);
    });
  }
  console.log(`[bot] @${config.TELEGRAM_BOT_USERNAME} running in ${config.BOT_MODE} mode`);

  const shutdown = async (signal: string) => {
    console.log(`[bot] ${signal} received, stopping`);
    bot.stop(signal);
    await stopNotifier();
    process.exit(0);
  };
  process.once("SIGINT", () => void shutdown("SIGINT"));
  process.once("SIGTERM", () => void shutdown("SIGTERM"));
}

main().catch((err) => {
  console.error("[bot] failed to start", err);
  process.exit(1);
});
