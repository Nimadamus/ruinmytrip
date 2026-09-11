-- Photos become things people can act on, not columns on something else.
--
-- A photograph already had a row (trip_photos, review_photos) and now has a page, so it needs the
-- two columns every other target of a like or a comment has: who owns it, and whether it is
-- published. Without them rmt_can_interact() cannot answer either question, and liking a photo
-- would have to mean liking the trip it came from, which is a different statement.
--
-- user_id is backfilled from the parent and is authoritative afterwards: a photo's owner is
-- whoever posted it, and a trip cannot change hands.
ALTER TABLE trip_photos   ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE trip_photos   ADD COLUMN IF NOT EXISTS status  TEXT NOT NULL DEFAULT 'published';
ALTER TABLE review_photos ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE review_photos ADD COLUMN IF NOT EXISTS status  TEXT NOT NULL DEFAULT 'published';

UPDATE trip_photos tp SET user_id = t.user_id
  FROM trips t WHERE t.id = tp.trip_id AND tp.user_id IS NULL;
UPDATE review_photos rp SET user_id = r.user_id
  FROM reviews r WHERE r.id = rp.review_id AND rp.user_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_trip_photos_user   ON trip_photos (user_id);
CREATE INDEX IF NOT EXISTS idx_review_photos_user ON review_photos (user_id);
