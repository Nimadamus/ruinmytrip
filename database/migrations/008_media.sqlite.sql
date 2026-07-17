-- sqlite mirror of 008 (SERIAL -> AUTOINCREMENT, BYTEA -> BLOB).
CREATE TABLE IF NOT EXISTS media (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  storage_key TEXT NOT NULL UNIQUE,
  driver TEXT NOT NULL DEFAULT 'pg',
  owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  mime TEXT NOT NULL,
  bytes INTEGER NOT NULL,
  width INTEGER,
  height INTEGER,
  sha256 TEXT,
  data BLOB,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_media_owner ON media(owner_id);
CREATE INDEX IF NOT EXISTS idx_media_sha ON media(sha256);
