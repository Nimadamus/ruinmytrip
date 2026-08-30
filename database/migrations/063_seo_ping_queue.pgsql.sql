-- See 063_seo_ping_queue.sqlite.sql.
CREATE TABLE IF NOT EXISTS seo_ping_queue (
  id SERIAL PRIMARY KEY,
  url TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL,
  sent_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_seo_ping_pending ON seo_ping_queue (sent_at, id);
