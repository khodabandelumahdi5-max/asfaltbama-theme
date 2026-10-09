import { NextResponse, type NextRequest } from "next/server";
import { STAKE_LAMPORTS, TREASURY_ADDRESS } from "@/lib/constants";
import { db } from "@/lib/db";
import { extractDeposits, heliusPayload, type DepositCandidate } from "@/lib/helius";
import { headerMatches } from "@/lib/secret-header";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

type DepositOutcome = "MATCHED" | "UNMATCHED" | "WRONG_AMOUNT" | "DUPLICATE_ENTRY" | "REPLAY";

function isAuthorized(req: NextRequest): boolean {
  const secret = process.env.HELIUS_WEBHOOK_SECRET;
  return !!secret && headerMatches(req.headers.get("authorization"), secret);
}

/**
 * Records one deposit and, if it is exactly one stake into an open pool,
 * enrolls the sender. Runs in its own transaction; a replayed signature is a no-op.
 */
async function processDeposit(d: DepositCandidate): Promise<DepositOutcome> {
  const client = await db.connect();
  try {
    await client.query("BEGIN");

    const inserted = await client.query(
      `INSERT INTO deposits (tx_sig, from_wallet, amount_lamports, slot, status)
       VALUES ($1, $2, $3, $4, 'UNMATCHED')
       ON CONFLICT (tx_sig) DO NOTHING
       RETURNING tx_sig`,
      [d.txSig, d.fromWallet, d.lamports, d.slot],
    );
    if (inserted.rowCount === 0) {
      await client.query("ROLLBACK");
      return "REPLAY";
    }

    let outcome: DepositOutcome;
    let poolId: string | null = null;

    if (d.lamports !== STAKE_LAMPORTS) {
      outcome = "WRONG_AMOUNT";
    } else {
      // Enrollment is open until the end of day 1.
      const pool = await client.query<{ id: string; stake_lamports: string }>(
        `SELECT id, stake_lamports FROM challenge_pools
          WHERE status = 'ACTIVE' AND start_date >= CURRENT_DATE
          ORDER BY start_date ASC
          LIMIT 1
          FOR UPDATE`,
      );

      if (pool.rowCount === 0 || Number(pool.rows[0].stake_lamports) !== d.lamports) {
        outcome = "UNMATCHED";
      } else {
        poolId = pool.rows[0].id;
        await client.query(
          `INSERT INTO users (wallet_address) VALUES ($1) ON CONFLICT DO NOTHING`,
          [d.fromWallet],
        );
        const joined = await client.query(
          `INSERT INTO pool_participants (pool_id, wallet_address, stake_lamports, deposit_tx_sig)
           VALUES ($1, $2, $3, $4)
           ON CONFLICT (pool_id, wallet_address) DO NOTHING`,
          [poolId, d.fromWallet, d.lamports, d.txSig],
        );
        outcome = joined.rowCount === 1 ? "MATCHED" : "DUPLICATE_ENTRY";
      }
    }

    await client.query(`UPDATE deposits SET status = $2, pool_id = $3 WHERE tx_sig = $1`, [
      d.txSig,
      outcome,
      poolId,
    ]);
    await client.query("COMMIT");
    return outcome;
  } catch (err) {
    await client.query("ROLLBACK").catch(() => {});
    throw err;
  } finally {
    client.release();
  }
}

export async function POST(req: NextRequest) {
  if (!isAuthorized(req)) {
    return NextResponse.json({ error: "unauthorized" }, { status: 401 });
  }
  if (!TREASURY_ADDRESS) {
    return NextResponse.json({ error: "treasury not configured" }, { status: 500 });
  }

  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "invalid json" }, { status: 400 });
  }

  const parsed = heliusPayload.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json({ error: "unexpected payload shape" }, { status: 400 });
  }

  const deposits = extractDeposits(parsed.data, TREASURY_ADDRESS);
  const results: { txSig: string; outcome: DepositOutcome }[] = [];

  try {
    for (const d of deposits) {
      results.push({ txSig: d.txSig, outcome: await processDeposit(d) });
    }
  } catch (err) {
    console.error("[webhook/solana] processing failed", err);
    // 5xx makes Helius retry; already-committed deposits replay as no-ops.
    return NextResponse.json({ error: "processing failed" }, { status: 500 });
  }

  return NextResponse.json({ received: parsed.data.length, deposits: results });
}
