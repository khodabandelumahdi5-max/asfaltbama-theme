import type { TournamentStatus, TournamentView } from "@arena/shared";
import { redis } from "../redis/client";
import { keys } from "../redis/keys";

export async function getActiveTournamentId(): Promise<string | null> {
  return redis.get(keys.activeTournament);
}

export async function getTournamentView(tid: string): Promise<TournamentView | null> {
  const m = await redis.hgetall(keys.tMeta(tid));
  if (!m.id) return null;
  return {
    id: m.id,
    name: m.name ?? "",
    status: (m.status ?? "SCHEDULED") as TournamentStatus,
    startsAt: Number(m.startsAt),
    endsAt: Number(m.endsAt),
    entryTickets: Number(m.entryTickets),
    startingBalance: Number(m.startingBalance),
    players: Number(m.players ?? 0),
    prizePoolTickets: Number(m.prizePool ?? 0),
  };
}

export async function getActiveTournament(): Promise<TournamentView | null> {
  const tid = await getActiveTournamentId();
  return tid ? getTournamentView(tid) : null;
}

export async function writeTournamentMeta(t: TournamentView): Promise<void> {
  await redis.hset(keys.tMeta(t.id), {
    id: t.id,
    name: t.name,
    status: t.status,
    startsAt: t.startsAt,
    endsAt: t.endsAt,
    entryTickets: t.entryTickets,
    startingBalance: t.startingBalance,
    players: t.players,
    prizePool: t.prizePoolTickets,
  });
}

export async function setTournamentStatus(tid: string, status: TournamentStatus): Promise<void> {
  await redis.hset(keys.tMeta(tid), "status", status);
}
