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
    joined_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (pool_id, wallet_address),
    CHECK (is_eliminated = (eliminated_on_day IS NOT NULL))
);
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

-- A proof may only be filed for today's day number, and only by a non-eliminated participant.
CREATE OR REPLACE FUNCTION lockin_check_submission() RETURNS trigger AS $$
DECLARE
    p_start DATE;
    eliminated BOOLEAN;
BEGIN
    SELECT start_date INTO p_start FROM challenge_pools WHERE id = NEW.pool_id;
    IF NEW.day_number <> (CURRENT_DATE - p_start) + 1 THEN
        RAISE EXCEPTION 'day_number % is not today''s challenge day', NEW.day_number;
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
