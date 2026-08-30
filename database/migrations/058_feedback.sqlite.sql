-- Migration 058 (sqlite) - see the pgsql file for why.

CREATE TABLE IF NOT EXISTS feedback (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    kind            TEXT NOT NULL,
    place_id        INTEGER REFERENCES places(id) ON DELETE CASCADE,
    message         TEXT NOT NULL,
    contact_email   TEXT,
    reported_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status          TEXT NOT NULL DEFAULT 'pending',
    resolved_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    resolved_at     TEXT,
    resolution_note TEXT,
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS feedback_status ON feedback (status, created_at);
CREATE INDEX IF NOT EXISTS feedback_place ON feedback (place_id, status);
