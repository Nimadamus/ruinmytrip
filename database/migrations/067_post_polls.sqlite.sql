-- Polls on posts.
--
-- Half of what travelers want to ask each other is a choice: "Lisbon or Porto for four days",
-- "is the sleeper train worth it or not". A question like that in prose gets three replies from
-- the three people who felt like typing; the same question with two buttons gets an answer from
-- everyone who has an opinion, and a number the next person can read at a glance.
--
-- One poll per post, two to four options, one vote per member per poll (changeable until the
-- poll closes). Counts are live COUNT(*) over poll_votes, never stored.
CREATE TABLE IF NOT EXISTS post_polls (
  post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE,
  closes_at TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS poll_options (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
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
