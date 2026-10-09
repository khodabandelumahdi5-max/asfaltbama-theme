import "server-only";
import { Pool } from "pg";

// Reuse a single pool across hot reloads in dev.
const globalForPg = globalThis as unknown as { pgPool?: Pool };

export const db =
  globalForPg.pgPool ??
  new Pool({
    connectionString: process.env.DATABASE_URL,
    max: 10,
    // Challenge days are UTC calendar days; make CURRENT_DATE agree everywhere.
    options: "-c TimeZone=UTC",
  });

if (process.env.NODE_ENV !== "production") globalForPg.pgPool = db;
