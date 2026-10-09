"use client";

import { useConnection, useWallet } from "@solana/wallet-adapter-react";
import { PublicKey, SystemProgram, Transaction } from "@solana/web3.js";
import bs58 from "bs58";
import { useCallback, useEffect, useState } from "react";
import { STAKE_LAMPORTS, TREASURY_ADDRESS } from "@/lib/constants";
import { buildSignedMessage, canonicalPayload } from "@/lib/signed-message";
import type { ArenaState } from "@/lib/types";

export function useArena(wallet: string | null) {
  const [state, setState] = useState<ArenaState | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const qs = wallet ? `?wallet=${encodeURIComponent(wallet)}` : "";
      const res = await fetch(`/api/arena${qs}`, { cache: "no-store" });
      if (!res.ok) throw new Error(`arena request failed (${res.status})`);
      setState((await res.json()) as ArenaState);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "failed to load arena");
    } finally {
      setLoading(false);
    }
  }, [wallet]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return { state, error, loading, refresh };
}

/** POSTs `payload` signed by the connected wallet, matching lib/signed-request.ts. */
export function useSignedPost() {
  const { publicKey, signMessage } = useWallet();

  return useCallback(
    async <T,>(url: string, action: string, payload: Record<string, unknown>): Promise<T> => {
      if (!publicKey) throw new Error("Connect a wallet first.");
      if (!signMessage) throw new Error("This wallet cannot sign messages.");

      const issuedAt = Date.now();
      const message = buildSignedMessage(action, canonicalPayload(payload), issuedAt);
      const signature = bs58.encode(await signMessage(new TextEncoder().encode(message)));

      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...payload, wallet: publicKey.toBase58(), issuedAt, signature }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error((json as { error?: string }).error ?? `request failed (${res.status})`);
      return json as T;
    },
    [publicKey, signMessage],
  );
}

/** Sends the 0.05 SOL stake to the treasury. Enrollment happens when the Helius webhook sees it. */
export function useStakeDeposit() {
  const { connection } = useConnection();
  const { publicKey, sendTransaction } = useWallet();

  return useCallback(async (): Promise<string> => {
    if (!publicKey) throw new Error("Connect a wallet first.");
    if (!TREASURY_ADDRESS) throw new Error("Treasury address is not configured.");

    const tx = new Transaction().add(
      SystemProgram.transfer({
        fromPubkey: publicKey,
        toPubkey: new PublicKey(TREASURY_ADDRESS),
        lamports: STAKE_LAMPORTS,
      }),
    );
    const latest = await connection.getLatestBlockhash("confirmed");
    const signature = await sendTransaction(tx, connection);
    await connection.confirmTransaction({ signature, ...latest }, "confirmed");
    return signature;
  }, [connection, publicKey, sendTransaction]);
}
