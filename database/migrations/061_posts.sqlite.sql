-- Posts: the short thing a traveler writes when they have something to say and no review to write.
--
-- Everything a member could publish here was heavyweight: a trip story, a review of a specific
-- place, a guide. All three assume you have already been somewhere and have something finished to
-- say about it. None of them is "is Lisbon awful in August" or "just landed, this hostel lied
-- about the shower". That is most of what people actually want to say to each other, and there
-- was nowhere to put it, so they said nothing.
--
-- A post optionally hangs off a city and/or a community. Both are nullable because the first post
-- somebody writes usually belongs to neither.
CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
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
