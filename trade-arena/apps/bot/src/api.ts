import type { AccountView, DepositNetwork, DepositView, LeaderboardPayload, TournamentView, UserProfile } from "@arena/shared";
import { config } from "./config";

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
  ) {
    super(message);
  }
}

async function request<T>(method: "GET" | "POST", path: string, body?: unknown): Promise<T> {
  const res = await fetch(`${config.SERVER_INTERNAL_URL}${path}`, {
    method,
    headers: {
      "x-internal-key": config.INTERNAL_API_KEY,
      ...(body !== undefined ? { "content-type": "application/json" } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(5_000),
  });
  const json = (await res.json().catch(() => ({}))) as { error?: string; code?: string };
  if (!res.ok) throw new ApiError(res.status, json.code ?? "ERROR", json.error ?? `Server responded ${res.status}`);
  return json as T;
}

export interface UpsertResult {
  user: UserProfile;
  referralLink: string;
  miniAppLink: string;
}

export interface Summary {
  user: UserProfile;
  referralLink: string;
  tournament: TournamentView | null;
  account: AccountView | null;
  leaderboard: LeaderboardPayload | null;
}

export const api = {
  upsertUser: (input: {
    id: number;
    firstName: string;
    lastName?: string;
    username?: string;
    languageCode?: string;
    isPremium?: boolean;
    startPayload?: string;
  }) => request<UpsertResult>("POST", "/internal/users/upsert", input),

  summary: (userId: number) => request<Summary>("GET", `/internal/users/${userId}/summary`),

  createDeposit: (userId: number, network: DepositNetwork, tickets: number) =>
    request<DepositView>("POST", "/internal/deposits", { userId: String(userId), network, tickets }),
};
