-- Migration 123 - appeals against a restriction. See the pgsql file.
CREATE TABLE IF NOT EXISTS trust_appeals (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body           TEXT    NOT NULL,
    status         TEXT    NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'granted', 'upheld')),
    created_at     TEXT NOT NULL,
    decided_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    decided_at     TEXT,
    decision_note  TEXT                                  -- the answer the member is shown
);
CREATE INDEX IF NOT EXISTS idx_trust_appeals_user ON trust_appeals (user_id, id);
CREATE INDEX IF NOT EXISTS idx_trust_appeals_open ON trust_appeals (status, id);
