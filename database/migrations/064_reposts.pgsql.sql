-- See 064_reposts.sqlite.sql.
ALTER TABLE posts ADD COLUMN IF NOT EXISTS repost_of INTEGER REFERENCES posts(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_posts_repost ON posts (repost_of);
