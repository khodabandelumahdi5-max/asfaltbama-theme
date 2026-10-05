import type { DepositNetwork, DepositView } from "@arena/shared";
import { SERVER_URL } from "./config";
import { authHeader } from "./telegram";

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(`${SERVER_URL}${path}`, {
    ...init,
    headers: { authorization: authHeader(), "content-type": "application/json", ...(init.headers ?? {}) },
  });
  const json = (await res.json().catch(() => ({}))) as { error?: string };
  if (!res.ok) throw new Error(json.error ?? `Request failed (${res.status})`);
  return json as T;
}

export interface MeResponse {
  referralLink: string;
  miniAppLink: string;
}

export const api = {
  me: () => request<MeResponse>("/api/me"),
  deposits: () => request<DepositView[]>("/api/payments/deposits"),
  createDeposit: (network: DepositNetwork, tickets: number) =>
    request<DepositView>("/api/payments/deposits", { method: "POST", body: JSON.stringify({ network, tickets }) }),
};
