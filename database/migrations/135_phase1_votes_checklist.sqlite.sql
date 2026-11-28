-- Migration 135 - Phase 1 votes and trip checklist. See the pgsql file.
CREATE TABLE IF NOT EXISTS warning_votes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    dest_slug  TEXT NOT NULL,
    item_key   TEXT NOT NULL,
    voter      TEXT NOT NULL,
    kind       TEXT NOT NULL CHECK (kind IN ('helpful', 'saved')),
    created_at TEXT NOT NULL,
    UNIQUE (dest_slug, item_key, voter, kind)
);
CREATE INDEX IF NOT EXISTS idx_warning_votes_dest ON warning_votes (dest_slug);
CREATE TABLE IF NOT EXISTS checklist_saves (
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    dest_slug  TEXT NOT NULL,
    item_key   TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (user_id, dest_slug, item_key)
);
