import { LAMPORTS_PER_SOL } from "@solana/web3.js";

export const CHALLENGE_DAYS = 21;
export const STAKE_SOL = 0.05;
export const STAKE_LAMPORTS = Math.round(STAKE_SOL * LAMPORTS_PER_SOL); // 50_000_000

export const SOLANA_RPC_URL =
  process.env.NEXT_PUBLIC_SOLANA_RPC_URL ?? "https://api.devnet.solana.com";

export const TREASURY_ADDRESS = process.env.NEXT_PUBLIC_TREASURY_ADDRESS ?? "";

/** devnet | testnet | mainnet-beta; only used for explorer links. */
export const SOLANA_CLUSTER = process.env.NEXT_PUBLIC_SOLANA_CLUSTER ?? "devnet";

export function explorerTxUrl(signature: string) {
  const cluster = SOLANA_CLUSTER === "mainnet-beta" ? "" : `?cluster=${SOLANA_CLUSTER}`;
  return `https://explorer.solana.com/tx/${signature}${cluster}`;
}
