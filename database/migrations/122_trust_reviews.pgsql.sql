-- Migration 122 - private vouches and flags, a trust score its owner can see, and the visible
-- "restricted" state.
--
-- After a buddy trip is over, or after two travelers were checked in to the same city room at the
-- same time, each may leave the other one private vouch or flag. Who said what is never shown to
-- the member it is about. What it adds up to is: their own vouch count, flag count, score and
-- state are on /trust, with the arithmetic, and every change of state is written to trust_events
-- and told to them. There is no hidden score and no shadowban (app/trust.php, app/trust_reviews.php).
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS trust_score INTEGER NOT NULL DEFAULT 0;
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS vouch_count INTEGER NOT NULL DEFAULT 0;
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS flag_count INTEGER NOT NULL DEFAULT 0;
-- ok | restricted (messaging paused, and the member is told so on every page)
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS trust_state TEXT NOT NULL DEFAULT 'ok';
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS trust_state_at TIMESTAMP;

CREATE TABLE IF NOT EXISTS user_reviews (
    id            SERIAL PRIMARY KEY,
    reviewer_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    subject_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    context_type  TEXT    NOT NULL CHECK (context_type IN ('bounty', 'checkin')),
    -- 'bounty:<buddy post id>' or 'checkin:<lower check-in id>:<higher check-in id>'. One review per
    -- reviewer, subject and meeting; kept as text so it outlives the rows it names.
    context_key   TEXT    NOT NULL,
    is_vouch      SMALLINT NOT NULL CHECK (is_vouch IN (0, 1)),    -- 1 = vouch, 0 = flag
    note          TEXT,
    -- counted | discounted (a moderator set it aside; it no longer counts and is kept as a record)
    status        TEXT    NOT NULL DEFAULT 'counted' CHECK (status IN ('counted', 'discounted')),
    created_at    TIMESTAMP NOT NULL,
    CHECK (reviewer_id <> subject_id),
    UNIQUE (reviewer_id, subject_id, context_key)
);
CREATE INDEX IF NOT EXISTS idx_user_reviews_subject ON user_reviews (subject_id, status, is_vouch);
CREATE INDEX IF NOT EXISTS idx_user_reviews_reviewer ON user_reviews (reviewer_id);

-- Every change of trust state, automatic or by a moderator, with the numbers at that moment.
CREATE TABLE IF NOT EXISTS trust_events (
    id           SERIAL PRIMARY KEY,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    from_state   TEXT NOT NULL,
    to_state     TEXT NOT NULL,
    reason       TEXT NOT NULL,                  -- flag_threshold | flags_cleared | moderator
    trust_score  INTEGER NOT NULL,
    vouch_count  INTEGER NOT NULL,
    flag_count   INTEGER NOT NULL,
    actor_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,   -- NULL = automatic
    note         TEXT,
    created_at   TIMESTAMP NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_trust_events_user ON trust_events (user_id, id);
