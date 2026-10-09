-- LockIn schema (PostgreSQL 13+; gen_random_uuid() is built in from 13).
-- Idempotent: safe to re-run via `npm run db:migrate`.

CREATE EXTENSION IF NOT EXISTS citext;

-- 1. Users, keyed by Solana wallet (base58, 32-44 chars)
CREATE TABLE IF NOT EXISTS users (
    wallet_address   VARCHAR(44) PRIMARY KEY
                     CHECK (wallet_address ~ '^[1-9A-HJ-NP-Za-km-z]{32,44}$'),
    username         CITEXT UNIQUE CHECK (char_length(username) BETWEEN 3 AND 32),
    reputation_score NUMERIC(3,2) NOT NULL DEFAULT 1.00
                     CHECK (reputation_score BETWEEN 0 AND 5),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. 21-day challenge pools
CREATE TABLE IF NOT EXISTS challenge_pools (
    id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title                 VARCHAR(120) NOT NULL,
    stake_lamports        BIGINT NOT NULL DEFAULT 50000000 CHECK (stake_lamports > 0), -- 0.05 SOL
    start_date            DATE NOT NULL,
    end_date              DATE NOT NULL,
    total_locked_lamports BIGINT NOT NULL DEFAULT 0 CHECK (total_locked_lamports >= 0),
    status                VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'
                          CHECK (status IN ('ACTIVE', 'COMPLETED', 'SETTLED')),
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (end_date - start_date = 20)  -- exactly 21 days inclusive
);

-- 3. Participants (streak tracker). A row exists only once a deposit is verified.
CREATE TABLE IF NOT EXISTS pool_participants (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pool_id           UUID NOT NULL REFERENCES challenge_pools(id) ON DELETE RESTRICT,
    wallet_address    VARCHAR(44) NOT NULL REFERENCES users(wallet_address) ON DELETE RESTRICT,
    stake_lamports    BIGINT NOT NULL CHECK (stake_lamports > 0),
    deposit_tx_sig    VARCHAR(88) NOT NULL UNIQUE,
    current_streak    INT NOT NULL DEFAULT 0 CHECK (current_streak BETWEEN 0 AND 21),
    is_eliminated     BOOLEAN NOT NULL DEFAULT FALSE,
    eliminated_on_day INT CHECK (eliminated_on_day BETWEEN 1 AND 21),
    elimination_reason VARCHAR(20)
                      CHECK (elimination_reason IN ('MISSED_DEADLINE', 'PEER_REJECTED')),
    joined_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (pool_id, wallet_address),
    CHECK (is_eliminated = (eliminated_on_day IS NOT NULL)),
    CONSTRAINT pool_participants_reason_consistent
        CHECK (is_eliminated = (elimination_reason IS NOT NULL))
);

-- Upgrade path for databases created before elimination_reason existed.
ALTER TABLE pool_participants ADD COLUMN IF NOT EXISTS elimination_reason VARCHAR(20)
    CHECK (elimination_reason IN ('MISSED_DEADLINE', 'PEER_REJECTED'));
UPDATE pool_participants SET elimination_reason = 'PEER_REJECTED'
 WHERE is_eliminated AND elimination_reason IS NULL;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pool_participants_reason_consistent') THEN
        ALTER TABLE pool_participants ADD CONSTRAINT pool_participants_reason_consistent
            CHECK (is_eliminated = (elimination_reason IS NOT NULL));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS pool_participants_wallet_idx ON pool_participants (wallet_address);

-- 4. Video proof submissions (Cloudflare Stream)
CREATE TABLE IF NOT EXISTS proof_submissions (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pool_id        UUID NOT NULL,
    wallet_address VARCHAR(44) NOT NULL,
    day_number     INT NOT NULL CHECK (day_number BETWEEN 1 AND 21),
    video_cf_id    VARCHAR(64) NOT NULL UNIQUE,
    status         VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                   CHECK (status IN ('PENDING', 'VERIFIED', 'REJECTED')),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (pool_id, wallet_address, day_number),
    UNIQUE (id, wallet_address),
    FOREIGN KEY (pool_id, wallet_address)
        REFERENCES pool_participants (pool_id, wallet_address) ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS proof_submissions_pending_idx
    ON proof_submissions (pool_id, created_at) WHERE status = 'PENDING';

-- 5. Peer review votes (social consensus)
CREATE TABLE IF NOT EXISTS peer_reviews (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    submission_id    UUID NOT NULL,
    submitter_wallet VARCHAR(44) NOT NULL,
    reviewer_wallet  VARCHAR(44) NOT NULL REFERENCES users(wallet_address),
    vote_value       SMALLINT NOT NULL CHECK (vote_value IN (-1, 1)),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (submission_id, reviewer_wallet),
    FOREIGN KEY (submission_id, submitter_wallet)
        REFERENCES proof_submissions (id, wallet_address) ON DELETE CASCADE,
    CHECK (reviewer_wallet <> submitter_wallet)
);
CREATE INDEX IF NOT EXISTS peer_reviews_reviewer_idx ON peer_reviews (reviewer_wallet);

-- 6. Raw deposit ledger written by the Helius webhook. tx_sig makes replays a no-op;
--    deposits that could not be matched to a pool stay UNMATCHED for refund.
CREATE TABLE IF NOT EXISTS deposits (
    tx_sig         VARCHAR(88) PRIMARY KEY,
    from_wallet    VARCHAR(44) NOT NULL,
    amount_lamports BIGINT NOT NULL CHECK (amount_lamports > 0),
    slot           BIGINT NOT NULL,
    pool_id        UUID REFERENCES challenge_pools(id),
    status         VARCHAR(20) NOT NULL
                   CHECK (status IN ('MATCHED', 'UNMATCHED', 'WRONG_AMOUNT', 'DUPLICATE_ENTRY')),
    received_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Keep challenge_pools.total_locked_lamports equal to the sum of participant stakes.
CREATE OR REPLACE FUNCTION lockin_sync_locked_total() RETURNS trigger AS $$
BEGIN
    UPDATE challenge_pools
       SET total_locked_lamports = total_locked_lamports + NEW.stake_lamports
     WHERE id = NEW.pool_id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS pool_participants_locked_total ON pool_participants;
CREATE TRIGGER pool_participants_locked_total
    AFTER INSERT ON pool_participants
    FOR EACH ROW EXECUTE FUNCTION lockin_sync_locked_total();

-- Day N is the UTC date start_date + N - 1. Its proof is due by the end of that
-- day plus this grace period (00:00 UTC + 6h = 06:00 UTC the next morning).
-- Single source of truth for the submission trigger, the jobs and the API.
CREATE OR REPLACE FUNCTION lockin_grace() RETURNS interval
    LANGUAGE sql IMMUTABLE AS $$ SELECT interval '6 hours' $$;

-- A proof may only be filed for today's day number (or yesterday's while the
-- grace period is still running), in an ACTIVE pool, by a non-eliminated participant.
CREATE OR REPLACE FUNCTION lockin_check_submission() RETURNS trigger AS $$
DECLARE
    p_start    DATE;
    p_status   VARCHAR(20);
    eliminated BOOLEAN;
    utc_today  DATE := (now() AT TIME ZONE 'UTC')::date;
    today_day  INT;
    in_grace   BOOLEAN;
BEGIN
    SELECT start_date, status INTO p_start, p_status FROM challenge_pools WHERE id = NEW.pool_id;
    IF p_status <> 'ACTIVE' THEN
        RAISE EXCEPTION 'pool is not active';
    END IF;

    today_day := utc_today - p_start + 1;
    in_grace  := now() < (utc_today::timestamp AT TIME ZONE 'UTC') + lockin_grace();
    IF NOT (NEW.day_number = today_day OR (in_grace AND NEW.day_number = today_day - 1)) THEN
        RAISE EXCEPTION 'day_number % is not open for submission', NEW.day_number;
    END IF;

    SELECT is_eliminated INTO eliminated FROM pool_participants
     WHERE pool_id = NEW.pool_id AND wallet_address = NEW.wallet_address;
    IF eliminated THEN
        RAISE EXCEPTION 'participant has been eliminated';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS proof_submissions_check ON proof_submissions;
CREATE TRIGGER proof_submissions_check
    BEFORE INSERT ON proof_submissions
    FOR EACH ROW EXECUTE FUNCTION lockin_check_submission();

-- 7. Settlement: one row per settled pool. The platform receives the 10% fee
--    plus the integer-division remainder ("dust"), so every lamport is accounted for.
CREATE TABLE IF NOT EXISTS pool_settlements (
    pool_id                      UUID PRIMARY KEY REFERENCES challenge_pools(id),
    total_lamports               BIGINT NOT NULL CHECK (total_lamports >= 0),
    platform_fee_lamports        BIGINT NOT NULL CHECK (platform_fee_lamports >= 0),
    dust_lamports                BIGINT NOT NULL CHECK (dust_lamports >= 0),
    survivor_count               INT NOT NULL CHECK (survivor_count >= 0),
    payout_per_survivor_lamports BIGINT NOT NULL CHECK (payout_per_survivor_lamports >= 0),
    escrow_wallet                VARCHAR(44) NOT NULL,
    platform_wallet              VARCHAR(44) NOT NULL,
    settled_at                   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (platform_fee_lamports + dust_lamports
           + survivor_count * payout_per_survivor_lamports = total_lamports)
);

-- 8. Payout ledger: one transfer per row, grouped into Solana transaction batches.
--    An executor signs each batch, then records tx_sig and moves status forward.
CREATE TABLE IF NOT EXISTS payouts (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pool_id          UUID NOT NULL REFERENCES pool_settlements(pool_id),
    recipient_wallet VARCHAR(44) NOT NULL,
    kind             VARCHAR(20) NOT NULL CHECK (kind IN ('SURVIVOR', 'PLATFORM_FEE')),
    amount_lamports  BIGINT NOT NULL CHECK (amount_lamports > 0),
    batch_index      INT NOT NULL CHECK (batch_index >= 0),
    status           VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                     CHECK (status IN ('PENDING', 'SENT', 'CONFIRMED', 'FAILED')),
    tx_sig           VARCHAR(88),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (pool_id, recipient_wallet, kind)
);
CREATE INDEX IF NOT EXISTS payouts_pending_idx ON payouts (pool_id, batch_index) WHERE status = 'PENDING';

-- 9. Payout attempts: one row per signed batch transaction, written BEFORE it is
--    broadcast. Its signature is the transaction id, so after a crash the
--    executor can ask the chain what happened instead of guessing. raw_tx lets it
--    rebroadcast the identical bytes, which can never land twice.
CREATE TABLE IF NOT EXISTS payout_attempts (
    tx_sig                 VARCHAR(88) PRIMARY KEY,
    pool_id                UUID NOT NULL REFERENCES pool_settlements(pool_id),
    batch_index            INT NOT NULL,
    raw_tx_base64          TEXT NOT NULL,
    last_valid_block_height BIGINT NOT NULL,
    status                 VARCHAR(20) NOT NULL DEFAULT 'SENT'
                           CHECK (status IN ('SENT', 'CONFIRMED', 'FAILED', 'EXPIRED')),
    error                  TEXT,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    resolved_at            TIMESTAMPTZ
);
-- At most one unresolved attempt per batch.
CREATE UNIQUE INDEX IF NOT EXISTS payout_attempts_one_open_per_batch
    ON payout_attempts (pool_id, batch_index) WHERE status = 'SENT';

ALTER TABLE pool_settlements ADD COLUMN IF NOT EXISTS paid_out_at TIMESTAMPTZ;

-- 10. Refunds for deposits that could not be enrolled (WRONG_AMOUNT, UNMATCHED,
--     DUPLICATE_ENTRY). The deposit signature is the primary key, so a deposit
--     can be refunded at most once. The executor creates these rows and verifies
--     each deposit on-chain before sending; anything that does not check out is
--     HELD for manual review instead of being paid.
CREATE TABLE IF NOT EXISTS refunds (
    deposit_tx_sig   VARCHAR(88) PRIMARY KEY REFERENCES deposits(tx_sig),
    recipient_wallet VARCHAR(44) NOT NULL,
    amount_lamports  BIGINT NOT NULL CHECK (amount_lamports > 0),
    escrow_wallet    VARCHAR(44) NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                     CHECK (status IN ('PENDING', 'SENT', 'CONFIRMED', 'HELD')),
    tx_sig           VARCHAR(88),
    note             TEXT,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS refunds_recipient_idx ON refunds (recipient_wallet);
CREATE INDEX IF NOT EXISTS refunds_pending_idx ON refunds (created_at) WHERE status = 'PENDING';
CREATE INDEX IF NOT EXISTS deposits_from_wallet_idx ON deposits (from_wallet);

-- Attempts now cover refund batches too: those have no pool or batch index.
ALTER TABLE payout_attempts ALTER COLUMN pool_id DROP NOT NULL;
ALTER TABLE payout_attempts ALTER COLUMN batch_index DROP NOT NULL;
ALTER TABLE payout_attempts ADD COLUMN IF NOT EXISTS kind VARCHAR(20) NOT NULL DEFAULT 'POOL_PAYOUT'
    CHECK (kind IN ('POOL_PAYOUT', 'REFUND'));
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'payout_attempts_kind_shape') THEN
        ALTER TABLE payout_attempts ADD CONSTRAINT payout_attempts_kind_shape CHECK (
            (kind = 'POOL_PAYOUT' AND pool_id IS NOT NULL AND batch_index IS NOT NULL)
         OR (kind = 'REFUND' AND pool_id IS NULL AND batch_index IS NULL));
    END IF;
END $$;
