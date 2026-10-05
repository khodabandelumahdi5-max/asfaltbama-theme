import { existsSync } from "node:fs";
import path from "node:path";
import type { NextConfig } from "next";

// Monorepo: share the root .env (NEXT_PUBLIC_* values) with the webapp.
const rootEnv = path.join(import.meta.dirname, "../../.env");
if (existsSync(rootEnv)) process.loadEnvFile(rootEnv);

const nextConfig: NextConfig = {
  reactStrictMode: true,
  output: "standalone",
  outputFileTracingRoot: path.join(import.meta.dirname, "../../"),
  transpilePackages: ["@arena/shared"],
  poweredByHeader: false,
  devIndicators: false,
  // Telegram renders the Mini App inside its own WebView/iframe; allow it to be framed by Telegram Web.
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "Content-Security-Policy", value: "frame-ancestors 'self' https://web.telegram.org https://*.telegram.org" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
        ],
      },
    ];
  },
};

export default nextConfig;
