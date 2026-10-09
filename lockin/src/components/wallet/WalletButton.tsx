"use client";

import dynamic from "next/dynamic";

// The button's label depends on localStorage (autoConnect) and injected
// wallets, which the server cannot know. Rendering it client-only avoids a
// hydration mismatch; the placeholder keeps the layout from shifting.
export const WalletButton = dynamic(
  () => import("@solana/wallet-adapter-react-ui").then((m) => m.WalletMultiButton),
  {
    ssr: false,
    loading: () => (
      <div className="h-12 w-44 border-3 border-obsidian-600 bg-obsidian-800 animate-pulse" aria-hidden />
    ),
  },
);
