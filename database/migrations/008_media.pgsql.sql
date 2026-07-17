-- Object storage, driver-agnostic.
--
-- Rows here are the SOURCE OF TRUTH for a stored file's identity (key, mime, size, checksum).
-- The bytes live either in this table (driver=pg) or in an external bucket (driver=r2), which is
-- why `data` is nullable: switching drivers must not require a schema change.
--
-- Postgres is not a long-term blob store, but it is the correct MVP choice here: the database
-- already has 15GB of disk, it is backed up with everything else, and no external credential
-- exists yet. app/storage.php abstracts this so moving to R2 later is a config change, not a
-- rewrite.
CREATE TABLE IF NOT EXISTS media (
  id SERIAL PRIMARY KEY,
  storage_key TEXT NOT NULL UNIQUE,
  driver TEXT NOT NULL DEFAULT 'pg',
  owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  mime TEXT NOT NULL,
  bytes INTEGER NOT NULL,
  width INTEGER,
  height INTEGER,
  sha256 TEXT,
  data BYTEA,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_media_owner ON media(owner_id);
CREATE INDEX IF NOT EXISTS idx_media_sha ON media(sha256);
