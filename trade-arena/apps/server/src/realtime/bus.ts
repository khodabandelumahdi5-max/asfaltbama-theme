import type { Namespace, Server } from "socket.io";
import {
  ROOMS,
  type ClientToServerEvents,
  type FeedEvent,
  type ServerToClientEvents,
  type SpectatorClientToServerEvents,
  type SpectatorServerToClientEvents,
} from "@arena/shared";
import { randomUUID } from "node:crypto";
import { redis } from "../redis/client";
import { keys } from "../redis/keys";

export type ArenaServer = Server<ClientToServerEvents, ServerToClientEvents>;
export type SpectatorNamespace = Namespace<SpectatorClientToServerEvents, SpectatorServerToClientEvents>;

let io: ArenaServer | null = null;
let spectators: SpectatorNamespace | null = null;

export function bindRealtime(server: ArenaServer, ns: SpectatorNamespace): void {
  io = server;
  spectators = ns;
}

type PublicEvent = keyof SpectatorServerToClientEvents;

/** Broadcast to every player in the arena room and every spectator (stream overlay). Cluster-wide via the Redis adapter. */
export function broadcast<E extends PublicEvent>(event: E, ...args: Parameters<ServerToClientEvents[E]>): void {
  if (!io || !spectators) return;
  (io.to(ROOMS.arena).emit as (e: string, ...a: unknown[]) => boolean)(event, ...args);
  (spectators.emit as (e: string, ...a: unknown[]) => boolean)(event, ...args);
}

/** High-frequency, droppable broadcast (market ticks). Slow clients skip frames instead of buffering. */
export function broadcastVolatile<E extends PublicEvent>(event: E, ...args: Parameters<ServerToClientEvents[E]>): void {
  if (!io || !spectators) return;
  (io.volatile.to(ROOMS.arena).emit as (e: string, ...a: unknown[]) => boolean)(event, ...args);
  (spectators.volatile.emit as (e: string, ...a: unknown[]) => boolean)(event, ...args);
}

export function emitToUser<E extends keyof ServerToClientEvents>(
  userId: string,
  event: E,
  ...args: Parameters<ServerToClientEvents[E]>
): void {
  if (!io) return;
  (io.to(ROOMS.user(userId)).emit as (e: string, ...a: unknown[]) => boolean)(event, ...args);
}

const FEED_LENGTH = 30;

export function publishFeed(event: Omit<FeedEvent, "id" | "ts">): void {
  const full: FeedEvent = { ...event, id: randomUUID(), ts: Date.now() };
  broadcast("feed:event", full);
  redis
    .multi()
    .lpush(keys.feed, JSON.stringify(full))
    .ltrim(keys.feed, 0, FEED_LENGTH - 1)
    .exec()
    .catch(() => undefined);
}
