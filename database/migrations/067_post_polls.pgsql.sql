-- See 067_post_polls.sqlite.sql.
CREATE TABLE IF NOT EXISTS post_polls (
  post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE,
  closes_at TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS poll_options (
  id SERIAL PRIMARY KEY,
  post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  position INTEGER NOT NULL,
  label TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_poll_options_post ON poll_options (post_id, position);
CREATE TABLE IF NOT EXISTS poll_votes (
  post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  option_id INTEGER NOT NULL REFERENCES poll_options(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  PRIMARY KEY (post_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_poll_votes_option ON poll_votes (option_id);
