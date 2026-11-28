-- Migration 135 - Phase 1 single player utility (2026-11-27, app/phase1.php).
-- warning_votes: "Helpful" / "Saved Me" on a city warning, no account needed. voter is 'u:<user id>'
--   for a member or 'c:<hash of a random browser cookie>' for a guest; the unique key makes a second
--   vote of the same kind from the same browser a no-op.
-- checklist_saves: a member's trip checklist. item_key '*' means the whole city.
CREATE TABLE IF NOT EXISTS warning_votes (
    id         SERIAL PRIMARY KEY,
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
