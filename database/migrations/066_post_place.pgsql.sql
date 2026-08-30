-- See 066_post_place.sqlite.sql.
ALTER TABLE posts ADD COLUMN IF NOT EXISTS place_id INTEGER REFERENCES places(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_posts_place ON posts (place_id, status);
