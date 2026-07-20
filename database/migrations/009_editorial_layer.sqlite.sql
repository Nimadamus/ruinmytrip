-- Editorial layer. See 009_editorial_layer.pgsql.sql for the full rationale.
-- SQLite has no ADD COLUMN IF NOT EXISTS; migrations run at most once, so plain ALTER is correct.

ALTER TABLE destinations ADD COLUMN hero_credit TEXT;
ALTER TABLE destinations ADD COLUMN hero_license TEXT;
ALTER TABLE destinations ADD COLUMN hero_source_url TEXT;

ALTER TABLE guides ADD COLUMN updated_at TEXT;

CREATE TABLE IF NOT EXISTS destination_tips (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_dest_tips ON destination_tips(destination_id, sort);

ALTER TABLE users ADD COLUMN referred_by TEXT;
