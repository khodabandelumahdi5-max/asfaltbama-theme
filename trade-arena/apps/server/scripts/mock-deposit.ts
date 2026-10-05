/**
 * Simulates a blockchain watcher confirming a USDT deposit.
 *
 *   pnpm --filter @arena/server mock:deposit -- --reference TA-ABCD-EFGH
 *   pnpm --filter @arena/server mock:deposit -- --network TRC20 --amount 5.004217
 *
 * Without --amount, the script looks up the pending deposit's exact amount.
 */
import { createHmac, randomBytes } from "node:crypto";
import { parseArgs } from "node:util";
import Database from "better-sqlite3";
import { resolve } from "node:path";

const { values } = parseArgs({
  options: {
    reference: { type: "string" },
    network: { type: "string" },
    amount: { type: "string" },
    tx: { type: "string" },
    url: { type: "string", default: `http://127.0.0.1:${process.env.SERVER_PORT ?? 4000}` },
  },
});

const secret = process.env.DEPOSIT_WEBHOOK_SECRET;
if (!secret) throw new Error("DEPOSIT_WEBHOOK_SECRET is not set");

let { network, amount } = values;
const reference = values.reference?.toUpperCase();
if (reference && (!amount || !network)) {
  const db = new Database(resolve(process.env.DATABASE_PATH ?? "./data/arena.db"), { readonly: true });
  const row = db.prepare("SELECT network, amount_micro FROM deposits WHERE reference = ?").get(reference) as
    | { network: string; amount_micro: number }
    | undefined;
  db.close();
  if (!row) throw new Error(`Deposit ${reference} not found`);
  network ??= row.network;
  amount ??= (row.amount_micro / 1_000_000).toFixed(6);
}
if (!network || !amount) throw new Error("Provide --reference, or --network and --amount");

const body = JSON.stringify({
  network,
  amount,
  asset: "USDT",
  txHash: values.tx ?? randomBytes(32).toString("hex"),
  ...(reference ? { reference } : {}),
  confirmations: 20,
});
const timestamp = String(Math.floor(Date.now() / 1000));
const signature = createHmac("sha256", secret).update(`${timestamp}.${body}`).digest("hex");

const res = await fetch(`${values.url}/api/webhooks/deposits`, {
  method: "POST",
  headers: { "content-type": "application/json", "x-timestamp": timestamp, "x-signature": signature },
  body,
});
console.log(res.status, JSON.stringify(await res.json(), null, 2));
