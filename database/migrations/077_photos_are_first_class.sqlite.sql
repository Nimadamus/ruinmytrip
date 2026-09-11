-- Photos become things people can act on, not columns on something else. See the pgsql file for
-- why. SQLite has no ADD COLUMN IF NOT EXISTS, and the migrator runs each file once, so these are
-- plain ALTERs.
ALTER TABLE trip_photos   ADD COLUMN user_id INTEGER;
ALTER TABLE trip_photos   ADD COLUMN status  TEXT NOT NULL DEFAULT 'published';
ALTER TABLE review_photos ADD COLUMN user_id INTEGER;
ALTER TABLE review_photos ADD COLUMN status  TEXT NOT NULL DEFAULT 'published';

UPDATE trip_photos SET user_id = (SELECT t.user_id FROM trips t WHERE t.id = trip_photos.trip_id)
 WHERE user_id IS NULL;
UPDATE review_photos SET user_id = (SELECT r.user_id FROM reviews r WHERE r.id = review_photos.review_id)
 WHERE user_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_trip_photos_user   ON trip_photos (user_id);
CREATE INDEX IF NOT EXISTS idx_review_photos_user ON review_photos (user_id);
