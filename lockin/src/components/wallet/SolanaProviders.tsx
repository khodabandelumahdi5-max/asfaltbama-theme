"use client";

import { ConnectionProvider, WalletProvider } from "@solana/wallet-adapter-react";
import { WalletModalProvider } from "@solana/wallet-adapter-react-ui";
import { useMemo, type ReactNode } from "react";
import { SOLANA_RPC_URL } from "@/lib/constants";

/**
 * Wallets are discovered through the Wallet Standard (Phantom, Solflare,
 * Backpack, ...), so no per-wallet adapters are bundled. The providers render
 * their children unchanged on the server; anything that reads wallet state
 * is gated behind `useHasMounted` so server and first client render match.
 */
export function SolanaProviders({ children }: { children: ReactNode }) {
  const wallets = useMemo(() => [], []);

  return (
    <ConnectionProvider endpoint={SOLANA_RPC_URL} config={{ commitment: "confirmed" }}>
      <WalletProvider wallets={wallets} autoConnect>
        <WalletModalProvider>{children}</WalletModalProvider>
      </WalletProvider>
    </ConnectionProvider>
  );
}
