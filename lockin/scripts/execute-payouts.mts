// Payout executor: signs and broadcasts the payout batches that settle-pool prepared.
//
//   npm run payouts                       # dry run: show what would be sent
//   npm run payouts -- --execute          # send every unpaid settled pool
//   npm run payouts -- --execute --pool <uuid>   # one pool, no refunds
//   npm run payouts -- --execute --only refunds  # or --only pools
//
// Env: DATABASE_URL, NEXT_PUBLIC_SOLANA_RPC_URL, ESCROW_KEYPAIR_PATH, PLATFORM_KEYPAIR_PATH
// (Solana CLI keypair JSON files). Run this from an operator machine, never the web server.
//
// No double payments: each batch transaction is signed first, and its signature
// (the transaction id) is committed to the database BEFORE broadcasting. After a
// crash, a rerun asks the chain what happened to that signature and only signs a
// replacement once the old one is provably dead (blockhash expired, never landed).
//
// Refunds: deposits the webhook could not enroll (WRONG_AMOUNT, UNMATCHED,
// DUPLICATE_ENTRY) get a refunds row, keyed by the deposit signature so each is
// refunded at most once. Before sending, the deposit is re-read from the chain at
// finalized commitment and must show exactly the recorded lamports moving from the
// depositor to escrow; otherwise the refund is HELD for manual review.

import { readFileSync, statSync } from "node:fs";
import { parseArgs } from "node:util";
import {
  Connection,
  Keypair,
  LAMPORTS_PER_SOL,
  PublicKey,
  SystemProgram,
  Transaction,
  type VersionedTransactionResponse,
} from "@solana/web3.js";
import bs58 from "bs58";
import pg from "pg";
import { checkDeposit, sumSystemTransfers, type DecodedInstruction } from "../src/jobs/depositVerify.ts";
import { chunk } from "../src/jobs/payoutMath.ts";
import { classifyAttempt, clusterName, GENESIS_HASH, leavesValidBalance } from "../src/jobs/payoutOutcome.ts";

const MAX_ATTEMPTS_PER_BATCH = 3;
const POLL_MS = 2_000;
const RESOLVE_TIMEOUT_MS = 5 * 60_000;
const TRANSFERS_PER_TX = 18; // matches TRANSFERS_PER_BATCH in src/jobs/settlePool.ts
const REFUNDABLE = ["WRONG_AMOUNT", "UNMATCHED", "DUPLICATE_ENTRY"];
/** A deposit the RPC cannot find at finalized commitment after this long is held, not retried. */
const MISSING_DEPOSIT_HOLD_MS = 10 * 60_000;

const { values: args } = parseArgs({
  options: {
    execute: { type: "boolean", default: false },
    pool: { type: "string" },
    "allow-mainnet": { type: "boolean", default: false },
    only: { type: "string" },
  },
});
if (args.only && args.only !== "pools" && args.only !== "refunds") throw new Error("--only must be pools or refunds");
const runPools = args.only !== "refunds";
const runRefunds = args.only !== "pools" && !args.pool;

function requireEnv(name: string): string {
  const v = process.env[name];
  if (!v) throw new Error(`${name} is not set`);
  return v;
}

function loadKeypair(envName: string): Keypair {
  const path = requireEnv(envName);
  if ((statSync(path).mode & 0o077) !== 0) {
    console.warn(`warning: ${path} is readable by other users; chmod 600 it`);
  }
  return Keypair.fromSecretKey(Uint8Array.from(JSON.parse(readFileSync(path, "utf8"))));
}

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
const sol = (lamports: bigint) => `${(Number(lamports) / LAMPORTS_PER_SOL).toFixed(9)} SOL`;

const db = new pg.Client({ connectionString: requireEnv("DATABASE_URL"), options: "-c TimeZone=UTC" });
const connection = new Connection(process.env.NEXT_PUBLIC_SOLANA_RPC_URL ?? "https://api.devnet.solana.com", "confirmed");
let explorerCluster = "";

async function inTx<T>(fn: () => Promise<T>): Promise<T> {
  await db.query("BEGIN");
  try {
    const out = await fn();
    await db.query("COMMIT");
    return out;
  } catch (err) {
    await db.query("ROLLBACK").catch(() => {});
    throw err;
  }
}

interface OpenAttempt {
  txSig: string;
  rawTx: Buffer;
  lastValidBlockHeight: number;
}

/**
 * Waits until the chain gives a definitive answer for this signature, rebroadcasting
 * the identical bytes meanwhile (same signature, so it can land at most once), then
 * records the answer. CONFIRMED marks the payouts or refunds paid; FAILED or EXPIRED puts them
 * back to PENDING so a fresh transaction can be signed.
 */
async function resolveAttempt(a: OpenAttempt): Promise<"CONFIRMED" | "FAILED" | "EXPIRED"> {
  const deadline = Date.now() + RESOLVE_TIMEOUT_MS;
  for (;;) {
    // Order matters: block height first, then status (see classifyAttempt).
    const finalizedBlockHeight = await connection.getBlockHeight("finalized");
    const { value } = await connection.getSignatureStatuses([a.txSig], { searchTransactionHistory: true });
    const status = value[0];
    const outcome = classifyAttempt({ status, finalizedBlockHeight, lastValidBlockHeight: a.lastValidBlockHeight });

    if (outcome !== "IN_FLIGHT") {
      const error = outcome === "FAILED" ? JSON.stringify(status?.err) : outcome === "EXPIRED" ? "blockhash expired" : null;
      await inTx(async () => {
        await db.query(
          `UPDATE payout_attempts SET status = $2, error = $3, resolved_at = NOW() WHERE tx_sig = $1`,
          [a.txSig, outcome, error],
        );
        // An attempt carries either pool payouts or refunds; updating both by tx_sig is exact.
        for (const table of ["payouts", "refunds"]) {
          await db.query(
            outcome === "CONFIRMED"
              ? `UPDATE ${table} SET status = 'CONFIRMED' WHERE tx_sig = $1`
              : `UPDATE ${table} SET status = 'PENDING', tx_sig = NULL WHERE tx_sig = $1`,
            [a.txSig],
          );
        }
      });
      console.log(`  ${outcome.padEnd(9)} ${a.txSig}${error ? `  (${error})` : ""}`);
      return outcome;
    }

    if (Date.now() > deadline) {
      throw new Error(`attempt ${a.txSig} still unresolved after ${RESOLVE_TIMEOUT_MS / 1000}s; rerun later, it stays SENT`);
    }
    if (!status) {
      await connection.sendRawTransaction(a.rawTx, { skipPreflight: true, maxRetries: 0 }).catch(() => {});
    }
    await sleep(POLL_MS);
  }
}

type Target = { kind: "POOL_PAYOUT"; poolId: string; batchIndex: number } | { kind: "REFUND" };
interface TransferItem {
  /** payouts.id or refunds.deposit_tx_sig */
  id: string;
  recipient: string;
  lamports: bigint;
}

/** Loads one pool batch's pending payouts and sends them. */
async function sendPoolBatch(poolId: string, batchIndex: number, escrow: Keypair, platform: Keypair) {
  const { rows } = await db.query<{ id: string; recipient_wallet: string; amount_lamports: string }>(
    `SELECT id, recipient_wallet, amount_lamports::text FROM payouts
      WHERE pool_id = $1 AND batch_index = $2 AND status = 'PENDING'
      ORDER BY (kind = 'PLATFORM_FEE') DESC, recipient_wallet`,
    [poolId, batchIndex],
  );
  const items = rows.map((r) => ({ id: r.id, recipient: r.recipient_wallet, lamports: BigInt(r.amount_lamports) }));
  return signAndSend(items, { kind: "POOL_PAYOUT", poolId, batchIndex }, escrow, platform);
}

/** Signs one transaction for `items`, records the attempt, broadcasts and resolves it. */
async function signAndSend(
  items: TransferItem[],
  target: Target,
  escrow: Keypair,
  platform: Keypair,
): Promise<"CONFIRMED" | "FAILED" | "EXPIRED"> {
  const total = items.reduce((sum, r) => sum + r.lamports, 0n);

  // Balance checks so a predictable failure never costs a fee.
  const [escrowBalance, payerBalance, rentMin] = await Promise.all([
    connection.getBalance(escrow.publicKey, "confirmed"),
    connection.getBalance(platform.publicKey, "confirmed"),
    connection.getMinimumBalanceForRentExemption(0),
  ]);
  if (!leavesValidBalance(BigInt(escrowBalance), total, BigInt(rentMin))) {
    throw new Error(
      `escrow holds ${sol(BigInt(escrowBalance))}; paying ${sol(total)} would leave it below zero or below the rent-exempt minimum`,
    );
  }

  const { blockhash, lastValidBlockHeight } = await connection.getLatestBlockhash("confirmed");
  const tx = new Transaction({ feePayer: platform.publicKey, blockhash, lastValidBlockHeight });
  for (const r of items) {
    tx.add(SystemProgram.transfer({ fromPubkey: escrow.publicKey, toPubkey: new PublicKey(r.recipient), lamports: r.lamports }));
  }
  const fee = (await connection.getFeeForMessage(tx.compileMessage(), "confirmed")).value ?? 10_000;
  if (payerBalance < fee) throw new Error(`platform fee payer holds ${payerBalance} lamports, needs ${fee}`);

  tx.sign(platform, escrow); // fee payer signs first; its signature is the tx id
  const txSig = bs58.encode(tx.signature!);
  const rawTx = tx.serialize();

  // Commit the intent before anything leaves this machine.
  await inTx(async () => {
    const claimed = await db.query(
      target.kind === "POOL_PAYOUT"
        ? `UPDATE payouts SET status = 'SENT', tx_sig = $2 WHERE id = ANY($1::uuid[]) AND status = 'PENDING'`
        : `UPDATE refunds SET status = 'SENT', tx_sig = $2 WHERE deposit_tx_sig = ANY($1::text[]) AND status = 'PENDING'`,
      [items.map((r) => r.id), txSig],
    );
    if (claimed.rowCount !== items.length) throw new Error("rows changed underneath the executor");
    await db.query(
      `INSERT INTO payout_attempts (tx_sig, kind, pool_id, batch_index, raw_tx_base64, last_valid_block_height)
       VALUES ($1, $2, $3, $4, $5, $6)`,
      [
        txSig,
        target.kind,
        target.kind === "POOL_PAYOUT" ? target.poolId : null,
        target.kind === "POOL_PAYOUT" ? target.batchIndex : null,
        rawTx.toString("base64"),
        lastValidBlockHeight,
      ],
    );
  });

  const label = target.kind === "POOL_PAYOUT" ? `batch ${target.batchIndex}` : "refund batch";
  console.log(`  ${label}: ${items.length} transfers, ${sol(total)}`);
  console.log(`  SENT      ${txSig}  https://explorer.solana.com/tx/${txSig}${explorerCluster}`);
  await connection.sendRawTransaction(rawTx, { skipPreflight: true, maxRetries: 0 }).catch((e) => {
    console.warn(`  broadcast error (will rebroadcast): ${e instanceof Error ? e.message : e}`);
  });
  return resolveAttempt({ txSig, rawTx, lastValidBlockHeight });
}

async function processPool(
  pool: { pool_id: string; escrow_wallet: string; platform_wallet: string },
  escrow: Keypair | null,
  platform: Keypair | null,
): Promise<boolean> {
  const poolId = pool.pool_id;
  console.log(`\npool ${poolId}`);

  if (escrow && platform) {
    if (escrow.publicKey.toBase58() !== pool.escrow_wallet || platform.publicKey.toBase58() !== pool.platform_wallet) {
      console.error(`  keypairs do not match this settlement (escrow ${pool.escrow_wallet}, platform ${pool.platform_wallet}); skipping`);
      return false;
    }
  }

  // 1. Finish anything a previous run left in flight.
  const open = await db.query<{ tx_sig: string; raw_tx_base64: string; last_valid_block_height: string }>(
    `SELECT tx_sig, raw_tx_base64, last_valid_block_height::text FROM payout_attempts
      WHERE pool_id = $1 AND status = 'SENT' ORDER BY created_at`,
    [poolId],
  );
  for (const a of open.rows) {
    if (!args.execute) {
      console.log(`  unresolved attempt ${a.tx_sig} (rerun with --execute to reconcile)`);
      continue;
    }
    console.log(`  reconciling ${a.tx_sig}`);
    await resolveAttempt({
      txSig: a.tx_sig,
      rawTx: Buffer.from(a.raw_tx_base64, "base64"),
      lastValidBlockHeight: Number(a.last_valid_block_height),
    });
  }

  // 2. Send every batch that still has pending payouts.
  const batches = await db.query<{ batch_index: number; n: number; total: string }>(
    `SELECT batch_index, COUNT(*)::int AS n, SUM(amount_lamports)::text AS total FROM payouts
      WHERE pool_id = $1 AND status = 'PENDING' GROUP BY batch_index ORDER BY batch_index`,
    [poolId],
  );
  for (const b of batches.rows) {
    if (!args.execute) {
      console.log(`  would send batch ${b.batch_index}: ${b.n} transfers, ${sol(BigInt(b.total))}`);
      continue;
    }
    let outcome: string = "EXPIRED";
    for (let attempt = 1; attempt <= MAX_ATTEMPTS_PER_BATCH && outcome === "EXPIRED"; attempt++) {
      outcome = await sendPoolBatch(poolId, b.batch_index, escrow!, platform!);
    }
    if (outcome !== "CONFIRMED") {
      console.error(`  batch ${b.batch_index} ${outcome}; stopping this pool. Its payouts are PENDING again.`);
      return false;
    }
  }

  // 3. Close the pool out once every payout is confirmed on-chain.
  const left = await db.query<{ n: number }>(
    `SELECT COUNT(*)::int AS n FROM payouts WHERE pool_id = $1 AND status <> 'CONFIRMED'`,
    [poolId],
  );
  if (left.rows[0].n === 0) {
    if (args.execute) await db.query(`UPDATE pool_settlements SET paid_out_at = NOW() WHERE pool_id = $1`, [poolId]);
    console.log(args.execute ? "  all payouts confirmed; pool paid out" : "  nothing left to send");
  }
  return true;
}

function decodedInstructions(tx: VersionedTransactionResponse): DecodedInstruction[] {
  const msg = tx.transaction.message;
  const keys =
    msg.version === 0
      ? msg.getAccountKeys({ accountKeysFromLookups: tx.meta?.loadedAddresses ?? undefined })
      : msg.getAccountKeys();
  const resolve = (programIdIndex: number, accounts: number[], data: Uint8Array): DecodedInstruction => ({
    programId: keys.get(programIdIndex)!,
    accounts: accounts.map((i) => keys.get(i)!),
    data,
  });
  const top = msg.compiledInstructions.map((ix) => resolve(ix.programIdIndex, ix.accountKeyIndexes, ix.data));
  const inner = (tx.meta?.innerInstructions ?? []).flatMap((g) =>
    g.instructions.map((ix) => resolve(ix.programIdIndex, ix.accounts, bs58.decode(ix.data))),
  );
  return [...top, ...inner];
}

async function processRefunds(escrow: Keypair | null, platform: Keypair | null): Promise<boolean> {
  console.log("\nrefunds");

  // 1. Finish refund attempts a previous run left in flight.
  const open = await db.query<{ tx_sig: string; raw_tx_base64: string; last_valid_block_height: string }>(
    `SELECT tx_sig, raw_tx_base64, last_valid_block_height::text FROM payout_attempts
      WHERE kind = 'REFUND' AND status = 'SENT' ORDER BY created_at`,
  );
  for (const a of open.rows) {
    if (!args.execute) {
      console.log(`  unresolved refund attempt ${a.tx_sig} (rerun with --execute to reconcile)`);
      continue;
    }
    console.log(`  reconciling ${a.tx_sig}`);
    await resolveAttempt({
      txSig: a.tx_sig,
      rawTx: Buffer.from(a.raw_tx_base64, "base64"),
      lastValidBlockHeight: Number(a.last_valid_block_height),
    });
  }

  if (!args.execute) {
    const q = await db.query<{ n: number; total: string | null }>(
      `SELECT COUNT(*)::int AS n, SUM(d.amount_lamports)::text AS total
         FROM deposits d LEFT JOIN refunds r ON r.deposit_tx_sig = d.tx_sig
        WHERE d.status = ANY($1) AND (r.deposit_tx_sig IS NULL OR r.status = 'PENDING')`,
      [REFUNDABLE],
    );
    const { n, total } = q.rows[0];
    console.log(n ? `  would verify and refund ${n} deposits, ${sol(BigInt(total ?? 0))}` : "  nothing to refund");
    return true;
  }

  // 2. Queue a refund for every refundable deposit that does not have one yet.
  const queued = await db.query(
    `INSERT INTO refunds (deposit_tx_sig, recipient_wallet, amount_lamports, escrow_wallet)
     SELECT tx_sig, from_wallet, amount_lamports, $2 FROM deposits WHERE status = ANY($1)
     ON CONFLICT (deposit_tx_sig) DO NOTHING`,
    [REFUNDABLE, escrow!.publicKey.toBase58()],
  );
  if (queued.rowCount) console.log(`  queued ${queued.rowCount} new refunds`);

  // 3. Verify each pending refund's deposit on-chain.
  const pending = await db.query<{
    deposit_tx_sig: string;
    recipient_wallet: string;
    amount_lamports: string;
    escrow_wallet: string;
    received_at: Date;
  }>(
    `SELECT r.deposit_tx_sig, r.recipient_wallet, r.amount_lamports::text, r.escrow_wallet, d.received_at
       FROM refunds r JOIN deposits d ON d.tx_sig = r.deposit_tx_sig
      WHERE r.status = 'PENDING' ORDER BY r.created_at`,
  );
  const hold = (sig: string, note: string) =>
    db.query(`UPDATE refunds SET status = 'HELD', note = $2 WHERE deposit_tx_sig = $1 AND status = 'PENDING'`, [sig, note]);

  const eligible: TransferItem[] = [];
  for (const r of pending.rows) {
    if (r.escrow_wallet !== escrow!.publicKey.toBase58()) {
      await hold(r.deposit_tx_sig, `deposit was made to escrow ${r.escrow_wallet}, not the loaded key`);
      console.log(`  HELD      ${r.deposit_tx_sig}  (escrow mismatch)`);
      continue;
    }
    const tx = await connection.getTransaction(r.deposit_tx_sig, {
      commitment: "finalized",
      maxSupportedTransactionVersion: 0,
    });
    const check = checkDeposit({
      found: tx
        ? {
            err: tx.meta ? tx.meta.err : "transaction has no status metadata",
            transferred: sumSystemTransfers(decodedInstructions(tx), r.recipient_wallet, r.escrow_wallet),
          }
        : null,
      expectedLamports: BigInt(r.amount_lamports),
      receivedAt: r.received_at,
      now: new Date(),
      missingAfterMs: MISSING_DEPOSIT_HOLD_MS,
    });
    if (check.ok) {
      eligible.push({ id: r.deposit_tx_sig, recipient: r.recipient_wallet, lamports: BigInt(r.amount_lamports) });
    } else if (check.retryLater) {
      console.log(`  waiting   ${r.deposit_tx_sig}  (${check.reason})`);
    } else {
      await hold(r.deposit_tx_sig, check.reason);
      console.log(`  HELD      ${r.deposit_tx_sig}  (${check.reason})`);
    }
  }
  if (eligible.length === 0) {
    console.log("  nothing to send");
    return true;
  }

  // 4. Send verified refunds, up to TRANSFERS_PER_TX per transaction.
  for (const group of chunk(eligible, TRANSFERS_PER_TX)) {
    let outcome: string = "EXPIRED";
    for (let attempt = 1; attempt <= MAX_ATTEMPTS_PER_BATCH && outcome === "EXPIRED"; attempt++) {
      outcome = await signAndSend(group, { kind: "REFUND" }, escrow!, platform!);
    }
    if (outcome !== "CONFIRMED") {
      console.error(`  refund batch ${outcome}; stopping refunds. They are PENDING again.`);
      return false;
    }
  }
  return true;
}

async function main() {
  await db.connect();

  // One executor at a time, across machines: session-level advisory lock.
  const lock = await db.query<{ ok: boolean }>(`SELECT pg_try_advisory_lock(hashtext('lockin:payout-executor')) AS ok`);
  if (!lock.rows[0].ok) throw new Error("another payout executor is running");

  const genesis = await connection.getGenesisHash();
  const cluster = clusterName(genesis);
  if (genesis === GENESIS_HASH.mainnet && !args["allow-mainnet"]) {
    throw new Error("RPC points at mainnet-beta; pass --allow-mainnet if that is intended");
  }
  explorerCluster = cluster === "mainnet" ? "" : cluster === "devnet" || cluster === "testnet" ? `?cluster=${cluster}` : "";
  console.log(`cluster: ${cluster} (${connection.rpcEndpoint})  mode: ${args.execute ? "EXECUTE" : "dry run"}`);

  const escrow = args.execute ? loadKeypair("ESCROW_KEYPAIR_PATH") : null;
  const platform = args.execute ? loadKeypair("PLATFORM_KEYPAIR_PATH") : null;

  const pools = await db.query<{ pool_id: string; escrow_wallet: string; platform_wallet: string }>(
    `SELECT pool_id, escrow_wallet, platform_wallet FROM pool_settlements
      WHERE paid_out_at IS NULL AND ($1::uuid IS NULL OR pool_id = $1::uuid)
      ORDER BY settled_at`,
    [args.pool ?? null],
  );
  let allOk = true;
  if (runPools) {
    if (pools.rowCount === 0) console.log("no unpaid settled pools");
    for (const p of pools.rows) {
      try {
        allOk = (await processPool(p, escrow, platform)) && allOk;
      } catch (err) {
        allOk = false;
        console.error(`  error: ${err instanceof Error ? err.message : err}`);
      }
    }
  }
  if (runRefunds) {
    try {
      allOk = (await processRefunds(escrow, platform)) && allOk;
    } catch (err) {
      allOk = false;
      console.error(`  error: ${err instanceof Error ? err.message : err}`);
    }
  }
  process.exitCode = allOk ? 0 : 1;
}

main()
  .catch((err) => {
    console.error(err instanceof Error ? err.message : err);
    process.exitCode = 1;
  })
  .finally(() => db.end());
