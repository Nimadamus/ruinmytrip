-- See 061_posts.sqlite.sql for why this table exists.
CREATE TABLE IF NOT EXISTS posts (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  collection_id INTEGER REFERENCES collections(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'published',
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_posts_recent ON posts (status, created_at);
CREATE INDEX IF NOT EXISTS idx_posts_dest ON posts (destination_id, status);
CREATE INDEX IF NOT EXISTS idx_posts_collection ON posts (collection_id, status);
CREATE INDEX IF NOT EXISTS idx_posts_user ON posts (user_id, status);
