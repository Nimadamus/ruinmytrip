-- Migration 048 - make the place_hours.closed and place_photos.is_cover flags integers on
-- Postgres, matching SQLite.
--
-- 047 declared them BOOLEAN on Postgres and INTEGER on SQLite. That is a schema difference the
-- application layer cannot paper over, and it took production down for two routes:
--
--     SQLSTATE[42883] operator does not exist: boolean = integer
--
-- because `WHERE is_cover = 1` is valid SQLite and invalid Postgres. The same mismatch had a
-- quieter and worse form waiting behind it: pdo_pgsql hands a boolean back as the string 't' or
-- 'f', and (bool) 'f' is TRUE in PHP, so a day explicitly marked closed would have been read as
-- open. A wrong "Open now" is exactly the kind of confident false statement this model exists to
-- avoid.
--
-- The fix is one storage type on both drivers rather than driver-conditional SQL at every call
-- site. Integer wins because SQLite has no real boolean and PHP's integer handling is identical
-- across both.
--
-- Both tables were created empty by 047 and nothing writes them yet, so recreating them loses
-- nothing. The DO block proves that rather than assuming it: if either table has rows, this
-- migration aborts and the deploy stops instead of quietly discarding data.

DO $do$
DECLARE n_hours BIGINT; n_photos BIGINT;
BEGIN
  SELECT COUNT(*) INTO n_hours  FROM place_hours;
  SELECT COUNT(*) INTO n_photos FROM place_photos;
  IF n_hours > 0 OR n_photos > 0 THEN
    RAISE EXCEPTION 'refusing to run 048: place_hours has % row(s) and place_photos has % row(s). '
                    'This migration recreates both tables and is only safe while they are empty. '
                    'Convert the columns in place instead.', n_hours, n_photos;
  END IF;
END $do$;

DROP TABLE IF EXISTS place_hours;
DROP TABLE IF EXISTS place_photos;

CREATE TABLE place_hours (
  id            SERIAL PRIMARY KEY,
  place_id      INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  day_of_week   SMALLINT NOT NULL,
  opens         TEXT,
  closes        TEXT,
  closed        SMALLINT NOT NULL DEFAULT 0,
  valid_from    TEXT,
  valid_through TEXT,
  sort          INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT place_hours_day_range CHECK (day_of_week >= 0 AND day_of_week <= 6),
  CONSTRAINT place_hours_flag CHECK (closed IN (0, 1)),
  CONSTRAINT place_hours_shape CHECK (
    (closed = 1 AND opens IS NULL AND closes IS NULL) OR
    (closed = 0 AND opens IS NOT NULL AND closes IS NOT NULL)
  )
);
CREATE INDEX idx_place_hours_place ON place_hours(place_id, day_of_week, sort);

CREATE TABLE place_photos (
  id              SERIAL PRIMARY KEY,
  place_id        INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  review_photo_id INTEGER REFERENCES review_photos(id) ON DELETE CASCADE,
  storage_key     TEXT,
  url             TEXT,
  caption         TEXT,
  alt_text        TEXT,
  credit          TEXT,
  license         TEXT,
  source_url      TEXT,
  uploaded_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
  width           INTEGER,
  height          INTEGER,
  bytes           INTEGER,
  is_cover        SMALLINT NOT NULL DEFAULT 0,
  sort            INTEGER NOT NULL DEFAULT 0,
  status          TEXT NOT NULL DEFAULT 'published',
  created_at      TEXT NOT NULL,
  CONSTRAINT place_photos_flag CHECK (is_cover IN (0, 1)),
  CONSTRAINT place_photos_has_image CHECK (storage_key IS NOT NULL OR url IS NOT NULL)
);
CREATE INDEX idx_place_photos_place ON place_photos(place_id, status, sort, id);
CREATE UNIQUE INDEX idx_place_photos_one_cover ON place_photos(place_id) WHERE is_cover = 1;
CREATE UNIQUE INDEX idx_place_photos_review_photo ON place_photos(review_photo_id) WHERE review_photo_id IS NOT NULL;
