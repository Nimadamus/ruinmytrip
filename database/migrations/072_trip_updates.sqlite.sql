-- See the pgsql version. SQLite has no ADD COLUMN IF NOT EXISTS and the migrator runs each file
-- exactly once, so a plain ADD COLUMN is right here.
ALTER TABLE posts ADD COLUMN trip_id INTEGER REFERENCES trips(id);
CREATE INDEX IF NOT EXISTS idx_posts_trip ON posts (trip_id, created_at);
