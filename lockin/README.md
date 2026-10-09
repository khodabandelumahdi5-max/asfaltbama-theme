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
npm run db:migrate           # applies db/schema.sql (idempotent)
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

**Webhook.** Every incoming transfer is written to the `deposits` ledger, keyed by transaction signature, so Helius retries and replays do nothing. A successful transfer of exactly 0.05 SOL to the treasury enrolls the sender in the open pool. Enrollment stays open through day 1. Anything else is recorded as `WRONG_AMOUNT`, `UNMATCHED` or `DUPLICATE_ENTRY` for manual refund.

**Auth.** Write endpoints need a wallet `signMessage` signature over the action, the payload and a timestamp. Signatures expire after 5 minutes, and database unique keys block replays within that window.

**Consensus.** A net vote of +3 verifies a proof and extends the submitter's streak. A net vote of −3 rejects it and eliminates the submitter. Self-votes, double votes and votes from non-participants are rejected.

**Hydration.** Wallet state only exists in the browser. `WalletButton` is loaded with `ssr: false`, and `ArenaDashboard` renders the server skeleton until `useHasMounted()` flips. Server and first client render therefore always match, including when `autoConnect` restores a wallet.

## Not built yet

- A daily job that eliminates participants who missed a day. Right now only rejected proofs eliminate.
- End-of-pool settlement: payouts from the treasury to survivors, and refunds for unmatched deposits.
- The treasury is a plain wallet. A production version should hold stakes in an on-chain escrow program.
