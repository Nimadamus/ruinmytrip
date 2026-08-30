-- URLs waiting to be announced to search engines.
--
-- IndexNow is only worth having if it is fast: telling Bing about a post the day after it was
-- written is barely better than waiting to be crawled. Submitting inside the request that created
-- the post would make publishing wait on somebody else's API, so the request writes a row and a
-- scheduled flush does the talking.
CREATE TABLE IF NOT EXISTS seo_ping_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  url TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL,
  sent_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_seo_ping_pending ON seo_ping_queue (sent_at, id);
