// Applies db/schema.sql to DATABASE_URL. Usage: npm run db:migrate
import { readFile } from "node:fs/promises";
import pg from "pg";

const url = process.env.DATABASE_URL;
if (!url) {
  console.error("DATABASE_URL is not set");
  process.exit(1);
}

const sql = await readFile(new URL("../db/schema.sql", import.meta.url), "utf8");
const client = new pg.Client({ connectionString: url });

await client.connect();
try {
  await client.query("BEGIN");
  await client.query(sql);
  await client.query("COMMIT");
  console.log("Schema applied.");
} catch (err) {
  await client.query("ROLLBACK");
  console.error("Migration failed:", err.message);
  process.exitCode = 1;
} finally {
  await client.end();
}
