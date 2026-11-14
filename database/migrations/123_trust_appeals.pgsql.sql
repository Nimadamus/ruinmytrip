-- Migration 123 - appeals against a restriction.
--
-- A restriction is a moderator's decision (never automatic, see app/trust_reviews.php), so the
-- member it lands on gets a way to answer it. One open appeal at a time; the moderator's answer is
-- shown to the member and the decision is written to trust_events and moderation_log.
CREATE TABLE IF NOT EXISTS trust_appeals (
    id             SERIAL PRIMARY KEY,
    user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body           TEXT    NOT NULL,
    status         TEXT    NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'granted', 'upheld')),
    created_at     TIMESTAMP NOT NULL,
    decided_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    decided_at     TIMESTAMP,
    decision_note  TEXT                                  -- the answer the member is shown
);
CREATE INDEX IF NOT EXISTS idx_trust_appeals_user ON trust_appeals (user_id, id);
CREATE INDEX IF NOT EXISTS idx_trust_appeals_open ON trust_appeals (status, id);
