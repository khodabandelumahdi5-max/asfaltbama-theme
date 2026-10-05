/**
 * PM2 production topology (single host). Two server instances behind nginx give
 * zero-downtime deploys: `pm2 reload deploy/ecosystem.config.cjs --only arena-server-a`
 * then `--only arena-server-b`. Each instance fails readiness, hands off Redis leadership,
 * disconnects its sockets (clients reconnect to the peer) and drains before exiting.
 */
const common = {
  cwd: __dirname + "/../apps/server",
  script: "dist/index.js",
  node_args: "--env-file=../../.env",
  exec_mode: "fork",
  kill_timeout: 25_000,
  listen_timeout: 15_000,
  max_memory_restart: "768M",
  env: { NODE_ENV: "production" },
};

module.exports = {
  apps: [
    { ...common, name: "arena-server-a", env: { ...common.env, SERVER_PORT: 4000, INSTANCE_ID: "server-a" } },
    { ...common, name: "arena-server-b", env: { ...common.env, SERVER_PORT: 4001, INSTANCE_ID: "server-b" } },
    {
      name: "arena-bot",
      cwd: __dirname + "/../apps/bot",
      script: "dist/index.js",
      node_args: "--env-file=../../.env",
      env: { NODE_ENV: "production" },
      kill_timeout: 10_000,
    },
    {
      name: "arena-webapp",
      cwd: __dirname + "/../apps/webapp",
      script: "node_modules/next/dist/bin/next",
      args: "start -p 3000",
      env: { NODE_ENV: "production" },
    },
    {
      name: "arena-overlay",
      cwd: __dirname + "/../apps/stream-overlay",
      script: "node_modules/vite/bin/vite.js",
      args: "preview --port 5174 --strictPort",
      env: { NODE_ENV: "production" },
    },
    {
      name: "arena-stream",
      cwd: __dirname + "/../apps/stream-overlay",
      script: "scripts/stream_to_youtube.sh",
      interpreter: "bash",
      autorestart: true,
      restart_delay: 5_000,
      env: { LAYOUT: "landscape" },
    },
  ],
};
