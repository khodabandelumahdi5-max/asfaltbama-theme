import { dirname, resolve } from "node:path";
import { existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { migrate } from "drizzle-orm/better-sqlite3/migrator";
import { db } from "./client";

/** Locates the `drizzle/` migrations folder both from src (tsx) and dist (bundled). */
function migrationsFolder(): string {
  const here = dirname(fileURLToPath(import.meta.url));
  const candidates = [resolve(here, "../../drizzle"), resolve(here, "../drizzle"), resolve(process.cwd(), "drizzle")];
  const found = candidates.find((p) => existsSync(resolve(p, "meta/_journal.json")));
  if (!found) throw new Error(`Drizzle migrations folder not found (looked in ${candidates.join(", ")})`);
  return found;
}

export function runMigrations(): void {
  migrate(db, { migrationsFolder: migrationsFolder() });
}
