# LockIn

21-day social commitment and elimination dApp on Solana devnet. Stake 0.05 SOL, post a daily video proof, and peers vote each proof legit or fraud. A rejected proof eliminates you.

## Stack

- Next.js 15 (App Router), React 19, TypeScript, Tailwind CSS 3
- `@solana/web3.js` and `@solana/wallet-adapter-react` (Wallet Standard auto-detection, devnet)
- PostgreSQL via `pg`
- Helius enhanced-transaction webhook for deposit verification
- Cloudflare Stream for proof videos

## Setup

```bash
cp .env.example .env.local   # fill in values
npm install
npm run db:migrate           # applies db/schema.sql (idempotent, also upgrades older schemas)
npm test                     # settlement math + payout outcome unit tests
npm run dev
```

Create a pool (dates are UTC; must span exactly 21 days):

```sql
INSERT INTO challenge_pools (title, start_date, end_date)
VALUES ('Cold Shower Gauntlet', CURRENT_DATE, CURRENT_DATE + 20);
```

### Helius webhook

In the Helius dashboard, create an **Enhanced** webhook on **devnet**:

- Transaction type: `TRANSFER`
- Account addresses: your `NEXT_PUBLIC_TREASURY_ADDRESS`
- Webhook URL: `https://<your-host>/api/webhook/solana`
- Auth header: the value of `HELIUS_WEBHOOK_SECRET`

## How it works

| Flow | Where |
| --- | --- |
| Stake: wallet sends 0.05 SOL to the treasury | `components/arena/hooks.ts` (`useStakeDeposit`) |
| Deposit verification and enrollment | `app/api/webhook/solana/route.ts` |
| Dashboard data | `app/api/arena/route.ts` |
| Video upload (Cloudflare direct upload) | `app/api/proofs/upload-url/route.ts` |
| Proof submission | `app/api/proofs/route.ts` |
| Peer review and consensus | `app/api/reviews/route.ts` |
| Daily missed-day elimination | `jobs/dailyElimination.ts` via `app/api/cron/daily-check` |
| End-of-pool settlement | `jobs/settlePool.ts` via `app/api/cron/settle-pool` |

**Webhook.** Every incoming transfer is written to the `deposits` ledger, keyed by transaction signature, so Helius retries and replays do nothing. A successful transfer of exactly 0.05 SOL to the treasury enrolls the sender in the open pool. Enrollment stays open through day 1. Anything else is recorded as `WRONG_AMOUNT`, `UNMATCHED` or `DUPLICATE_ENTRY` for manual refund.

**Auth.** Write endpoints need a wallet `signMessage` signature over the action, the payload and a timestamp. Signatures expire after 5 minutes, and database unique keys block replays within that window.

**Consensus.** A net vote of +3 verifies a proof and extends the submitter's streak. A net vote of −3 rejects it and eliminates the submitter. Self-votes, double votes and votes from non-participants are rejected.

**Hydration.** Wallet state only exists in the browser. `WalletButton` is loaded with `ssr: false`, and `ArenaDashboard` renders the server skeleton until `useHasMounted()` flips. Server and first client render therefore always match, including when `autoConnect` restores a wallet.

## Deadlines

Day *N* is the UTC date `start_date + N − 1`. Its proof is due by **06:00 UTC the next morning**, so there is a 6-hour grace period after midnight. During that window the app offers yesterday's day before today's. The grace length is defined once, in the SQL function `lockin_grace()`. The submission trigger, both jobs and the arena API all read it from there.

## Cron jobs

Both endpoints accept GET or POST and require `Authorization: Bearer $CRON_SECRET`. If `CRON_SECRET` is unset or shorter than 16 characters, they refuse every request with 503. Both are idempotent, so running them twice is harmless. `vercel.json` schedules them at 06:05 and 06:15 UTC. Vercel Cron sends the bearer header automatically once `CRON_SECRET` is set on the project.

```bash
curl -H "Authorization: Bearer $CRON_SECRET" https://<host>/api/cron/daily-check
curl -H "Authorization: Bearer $CRON_SECRET" https://<host>/api/cron/settle-pool
```

**`daily-check`**
- Eliminates every live participant who has no proof for a day whose deadline has passed. The reason is recorded as `elimination_reason = 'MISSED_DEADLINE'` and the first missed day as `eliminated_on_day`.
- A proof still `PENDING` review counts as submitted.
- The streak is frozen, not reset: `current_streak` keeps the verified count the participant reached.
- Pools whose day-21 deadline has passed move to `COMPLETED`.
- Each elimination is logged as a JSON line (`event: participant_eliminated`).

**`settle-pool`**
For every `ACTIVE` or `COMPLETED` pool past its final deadline, in one transaction per pool:
1. Run a final elimination pass, so someone who missed day 21 is never paid even if `daily-check` didn't run.
2. Check that `total_locked_lamports` equals the sum of stakes. On a mismatch, that pool is skipped and reported, and the endpoint returns 500.
3. Split the pool:
   - platform fee = `floor(total × 10%)`
   - each survivor gets `floor(total × 90% / survivors)`
   - the integer remainder ("dust") goes to the platform, so payouts sum exactly to the pool total
   - with zero survivors, the whole pool goes to the platform
4. Write `pool_settlements` and one `payouts` row per transfer, grouped into batches of 18 (one Solana transaction each).
5. Set the pool to `SETTLED`.

The response contains each batch as unsigned `SystemProgram.transfer` instructions (program ID, account keys, base64 data). Every transfer sends from the escrow (`NEXT_PUBLIC_TREASURY_ADDRESS`). The platform wallet (`PLATFORM_TREASURY_ADDRESS`) is the fee payer, so network fees never eat into escrow. Each batch therefore needs both signatures.

## Sending payouts

`scripts/execute-payouts.mts` signs and broadcasts the batches that `settle-pool` prepared. Run it from an operator machine that holds the keys, never from the web server.

```bash
# .env.local (or exported): DATABASE_URL, NEXT_PUBLIC_SOLANA_RPC_URL,
# ESCROW_KEYPAIR_PATH, PLATFORM_KEYPAIR_PATH (Solana CLI keypair JSON, chmod 600)
npm run payouts                         # dry run: lists what would be sent
npm run payouts -- --execute            # pay every settled, unpaid pool
npm run payouts -- --execute --pool <uuid>
```

**No double payments.** For each batch, the executor:
1. Signs the transaction. The fee payer's signature is the transaction id, so it is known before anything is sent.
2. Commits that signature to the database before broadcasting: the payout rows become `SENT`, and a `payout_attempts` row stores the signed bytes and the blockhash's last valid block height.
3. Broadcasts, then waits until the chain gives a final answer. While waiting it rebroadcasts the identical bytes, which can land at most once.
4. Records the answer:
   - **finalized, success:** payouts become `CONFIRMED`.
   - **finalized, failed:** the transaction is atomic, so nothing moved. Payouts go back to `PENDING` and the pool stops.
   - **never landed and its blockhash expired:** it can never land, so payouts go back to `PENDING` and a fresh transaction is signed (up to 3 tries).

If the executor dies at any point, the next run reconciles each `SENT` attempt with the chain first. It never signs a replacement while the old transaction could still land. When every payout of a pool is `CONFIRMED`, `pool_settlements.paid_out_at` is set.

**Safety checks:**
- It refuses to start if another executor is running (Postgres advisory lock).
- It refuses mainnet unless you pass `--allow-mainnet`.
- It skips a pool whose settlement wallets don't match the loaded keys.
- Before signing, it checks that the escrow covers the batch and won't be left between zero and the rent-exempt minimum, and that the fee payer can cover the network fee.

The exit code is non-zero if any pool failed. Rerunning is always safe.

## Not built yet

- Refunds for `UNMATCHED`, `WRONG_AMOUNT` and `DUPLICATE_ENTRY` deposits.
- After settlement the arena only shows `ACTIVE` pools, so survivors don't see their payout in the UI yet.
- The treasury is a plain wallet. A production version should hold stakes in an on-chain escrow program.
