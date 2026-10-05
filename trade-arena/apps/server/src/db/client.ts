import { mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import Database from "better-sqlite3";
import { drizzle } from "drizzle-orm/better-sqlite3";
import { config } from "../config";
import * as schema from "./schema";

const dbPath = resolve(config.DATABASE_PATH);
mkdirSync(dirname(dbPath), { recursive: true });

export const sqlite = new Database(dbPath);
// WAL gives concurrent readers + a single writer without blocking reads; multiple
// server processes on the same host can share this file safely.
sqlite.pragma("journal_mode = WAL");
sqlite.pragma("synchronous = NORMAL");
sqlite.pragma("foreign_keys = ON");
sqlite.pragma("busy_timeout = 5000");
sqlite.pragma("cache_size = -32000");
sqlite.pragma("temp_store = MEMORY");

export const db = drizzle(sqlite, { schema });
export type DB = typeof db;

export function closeDb(): void {
  if (sqlite.open) sqlite.close();
}
