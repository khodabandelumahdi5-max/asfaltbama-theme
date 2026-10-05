# ⚡ Trade Arena

A skill-based, fast paper-trading tournament platform delivered as a **Telegram Mini App**. It has a horizontally scalable realtime backend and a **24/7 YouTube Live** broadcast of the arena.

| App | Stack | Role |
| --- | --- | --- |
| `apps/server` | Fastify 5, Socket.io 4 + Redis adapter, ioredis, Drizzle + SQLite (WAL) | Price feed, trading engine, ZSET leaderboard, tournaments, payments, auth |
| `apps/bot` | Telegraf 4 | `/start` deep links + referrals, Mini App launch, deposits, push notifications |
| `apps/webapp` | Next.js 15 (App Router), Tailwind 4, Zustand 5, Lightweight-Charts 5, socket.io-client | Telegram Mini App trading UI |
| `apps/stream-overlay` | Vite 6 + React 19, Lightweight-Charts, qrcode.react, Puppeteer, FFmpeg, Xvfb | 1080×1920 / 1920×1080 broadcast overlay + capture pipeline |
| `packages/shared` | TypeScript | Socket contracts, domain types, PnL/liquidation math |

## Directory tree

```
trade-arena/
├── package.json                    # pnpm workspace root scripts
├── pnpm-workspace.yaml
├── tsconfig.base.json
├── docker-compose.yml              # Redis 7 (AOF persistence)
├── .env.example                    # every env var for every app
├── deploy/
│   ├── ecosystem.config.cjs        # PM2: 2× server (rolling reload), bot, webapp, overlay, stream
│   └── nginx.conf                  # WebSocket-aware load balancing, /internal blocked
├── packages/shared/src/
│   ├── constants.ts                # symbol, leverage/fee/MMR limits, rooms, namespaces
│   ├── types.ts                    # domain types + typed Socket.io event maps
│   ├── math.ts                     # liquidation price, unrealized/net PnL, ROE
│   └── index.ts
├── apps/server/
│   ├── drizzle/                    # generated SQL migrations (drizzle-kit)
│   ├── drizzle.config.ts
│   ├── tsup.config.ts
│   ├── scripts/
│   │   ├── mock-deposit.ts         # signs + sends a deposit webhook (chain watcher stand-in)
│   │   ├── simulate-traders.ts     # N bot traders (demo data + socket load test)
│   │   └── e2e-smoke.ts            # end-to-end test against a running server
│   └── src/
│       ├── index.ts                # bootstrap, leader workloads, graceful zero-downtime shutdown
│       ├── config.ts  logger.ts
│       ├── auth/telegram.ts        # initData HMAC validation (+ dev identity)
│       ├── cluster/leader.ts       # Redis-lease leader election
│       ├── db/{schema,client,migrate,migrate-cli}.ts
│       ├── redis/{client,keys,scripts}.ts   # atomic Lua: open / close / join / lock
│       ├── market/{simulator,state}.ts      # GBM + stoch-vol + jumps price process, candle builder
│       ├── trading/engine.ts       # open/close/liquidation sweeps, trade persistence
│       ├── leaderboard/leaderboard.ts       # ZADD / ZREVRANGE WITHSCORES, coalesced broadcaster
│       ├── tournament/{manager,store}.ts    # SCHEDULED→ACTIVE→SETTLING→FINISHED, prizes
│       ├── services/{users,ledger,payments}.ts  # referrals, ticket ledger, deposits
│       ├── realtime/{socket,bus,notify}.ts  # Socket.io server, /spectator namespace, rate limits
│       └── http/{app,auth}.ts + routes/{public,webhooks,internal}.ts
├── apps/bot/src/
│   ├── index.ts                    # commands, deep links, deposit flow, menu button
│   ├── api.ts  config.ts  messages.ts
│   └── notifier.ts                 # Redis pub/sub → rate-limited Telegram DMs
├── apps/webapp/src/
│   ├── app/{layout,page}.tsx  app/globals.css
│   ├── lib/{socket,telegram,api,format,config}.ts
│   ├── store/{market,session,toast}.ts      # Zustand
│   ├── hooks/{useDerived,useNow}.ts
│   └── components/                 # ArenaApp, Header, PriceChart, OrderPanel, PositionCard,
│                                   # Leaderboard, WalletPanel, JoinCard, ResultModal, …
└── apps/stream-overlay/
    ├── index.html  vite.config.ts
    ├── src/                        # App, socket (spectator), store, components/*
    ├── capture/
    │   ├── browser.ts              # shared Puppeteer launch/ready/heartbeat helpers
    │   ├── record.ts               # headless CDP screencast → CFR 60fps → FFmpeg (file/RTMP)
    │   └── kiosk.ts                # Chromium kiosk on Xvfb, self-healing
    └── scripts/
        ├── stream_to_youtube.sh    # Xvfb + kiosk + FFmpeg x11grab → YouTube, supervised 24/7
        └── arena-stream.service    # systemd unit
```

## Architecture highlights

**Leaderboard engine.** Each closed trade runs one atomic Lua script. It settles the balance, then calls `ZADD {t:<id>}:lb <pnlPct> <userId>` and sets a dirty flag. The leader polls the flag every 200 ms with `GETDEL`. When it is set, the leader runs `ZREVRANGE … 0 9 WITHSCORES` and an `HMGET` for display names (sub-millisecond in Redis). It then broadcasts `leaderboard:top` to the arena room and to the `/spectator` namespace. A burst of trades produces at most one frame per 200 ms.

**Trading simulator.** The price process is GBM with stochastic volatility, momentum, jumps and weak mean reversion. It ticks every 250 ms and builds 5 s candles. Positions use isolated margin, leverage from 1× to 100×, a 0.04 % taker fee and a 0.5 % maintenance margin. Liquidation prices are indexed in two ZSETs, so each tick's liquidation sweep is a `ZRANGEBYSCORE`, which is O(log N + K). Open, close and join are Lua scripts, so concurrent manual closes and liquidations can never double-settle.

**Zero downtime.**
- Any number of server instances can share Redis. The Socket.io Redis adapter fans out emits across instances, and live state lives in Redis.
- A Redis lease (`SET NX PX` with compare-and-renew) elects one leader for the singleton work: price feed, liquidations, leaderboard flush, tournament scheduler and deposit expiry.
- On `SIGTERM` an instance fails `/health/ready`, releases the lease (a peer takes over in about 1 s), disconnects only its own sockets (clients reconnect to the peer) and drains HTTP.
- Tournament settlement is idempotent, so a new leader resumes a half-finished payout safely.

**Auth.** The Mini App sends `Telegram.WebApp.initData`. The server checks its HMAC-SHA256 with the key `HMAC("WebAppData", botToken)` and enforces `auth_date` freshness. REST calls use `Authorization: tma <initData>`. Outside Telegram, a `dev` identity is accepted only when `ALLOW_DEV_AUTH=true` and `NODE_ENV` is not `production`.

**Payments.** A deposit intent gets a unique reference such as `TA-7KQ2-M9XD`. On TON the reference is used as the memo. On TRC20 the amount carries a unique micro-tag such as `5.004217`, because TRC20 transfers have no memo; a partial unique index keeps that amount unique among pending intents. The webhook at `POST /api/webhooks/deposits` checks `x-signature = hex(HMAC_SHA256(secret, "<x-timestamp>.<rawBody>"))` with a 5-minute replay window. It is idempotent per tx hash and credits tickets plus the first-deposit referral bonus in a single SQLite transaction.

## Local development

Prerequisites: Node ≥ 22, pnpm 10 (`corepack enable`), plus Docker *or* a local `redis-server`. The streaming scripts also need `ffmpeg` and `xvfb`.

```bash
# 1. Install
cd trade-arena
corepack enable
pnpm install

# 2. Configure (set TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME, WEBAPP_URL at minimum)
cp .env.example .env

# 3. Redis
docker compose up -d redis          # or: redis-server --daemonize yes

# 4. Database migrations (also run automatically on server boot)
pnpm db:migrate

# 5. Run everything (server :4000, webapp :3000, overlay :5174, bot polling)
pnpm dev
#    …or individually:
pnpm dev:server
pnpm dev:webapp
pnpm dev:overlay
pnpm dev:bot
```

Then try the pieces:

- **Mini App in a browser:** open http://localhost:3000. With `ALLOW_DEV_AUTH=true` you get a dev identity and 3 welcome tickets.
- **Inside Telegram:** Telegram requires HTTPS. Expose the webapp and the server with a tunnel, for example `cloudflared tunnel --url http://localhost:3000` and the same for `:4000`. Set `WEBAPP_URL` to the webapp tunnel URL and `NEXT_PUBLIC_SERVER_URL` to the server tunnel URL, then add the webapp origin to `CORS_ORIGINS`. Register the Mini App with BotFather (`/newapp`) using the same URL, then send `/start` to the bot.
- **Stream overlay:** portrait is http://localhost:5174/?layout=portrait and landscape is `?layout=landscape`.
- **Demo traders:** `pnpm --filter @arena/server sim:traders -- --count 25` (needs `ALLOW_DEV_AUTH=true`).
- **Mock a deposit:** create one in the Wallet tab or via `/deposit` in the bot, then run `pnpm --filter @arena/server mock:deposit -- --reference TA-XXXX-XXXX`.
- **E2E smoke test:** `pnpm --filter @arena/server test:e2e`.

### Video capture & 24/7 YouTube Live

```bash
pnpm --filter @arena/stream-overlay build && pnpm --filter @arena/stream-overlay preview   # serve overlay on :5174

# Headless 60fps clip (no display server needed)
pnpm --filter @arena/stream-overlay capture:record -- --layout portrait --duration 60 --out recordings/clip.mp4

# 24/7 broadcast: Xvfb + Chromium kiosk + FFmpeg x11grab → YouTube (self-restarting)
YOUTUBE_STREAM_KEY=xxxx-xxxx-xxxx-xxxx LAYOUT=landscape pnpm --filter @arena/stream-overlay stream
# Dry run to a file:
OUTPUT_OVERRIDE=/tmp/test.mp4 MAX_RUNTIME_SEC=20 bash apps/stream-overlay/scripts/stream_to_youtube.sh
```

Set `VIDEO_ENCODER=h264_nvenc` (NVIDIA) or `h264_vaapi` (Intel/AMD) to move encoding off the CPU.

## Production

```bash
pnpm install --frozen-lockfile && pnpm build
pm2 start deploy/ecosystem.config.cjs
# Zero-downtime deploy: rebuild, then roll the API instances one at a time
pm2 reload deploy/ecosystem.config.cjs --only arena-server-a && pm2 reload deploy/ecosystem.config.cjs --only arena-server-b
```

Put `deploy/nginx.conf` in front of the stack. Keep `/internal/*` reachable only on loopback or a private network. Set `ALLOW_DEV_AUTH=false` and use strong values for `INTERNAL_API_KEY` and `DEPOSIT_WEBHOOK_SECRET`. Point your TronGrid or TonAPI watcher at `/api/webhooks/deposits` using the signing scheme above.
