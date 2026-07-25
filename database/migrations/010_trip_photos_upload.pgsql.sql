-- Trip photo uploads. trip_photos existed in the original schema (url, caption, sort) but
-- nothing ever wrote to it -- trip_new.php only ever had a pasted cover-image URL field, so
-- trip_show.php's photo gallery loop rendered against an always-empty table. Bringing it up to
-- the same shape as review_photos (migration 004) so the real upload pipeline (rmt_upload_image,
-- EXIF/GPS stripping, storage_key for deletion) can be reused instead of duplicated.
ALTER TABLE trip_photos ADD COLUMN IF NOT EXISTS storage_key TEXT;
ALTER TABLE trip_photos ADD COLUMN IF NOT EXISTS width INTEGER;
ALTER TABLE trip_photos ADD COLUMN IF NOT EXISTS height INTEGER;
ALTER TABLE trip_photos ADD COLUMN IF NOT EXISTS bytes INTEGER;
ALTER TABLE trip_photos ADD COLUMN IF NOT EXISTS created_at TEXT;
CREATE INDEX IF NOT EXISTS idx_trip_photos_trip ON trip_photos(trip_id, sort);
