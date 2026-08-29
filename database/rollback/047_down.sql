-- Manual rollback for migration 047 (Postgres). NOT run by the migrator.
--
-- The migrator is forward-only by design: an additive migration needs no automatic down step,
-- because the previous code release keeps working unchanged against the newer schema. That is the
-- normal rollback and it is a redeploy, not a SQL script.
--
-- This file exists for the one case redeploying does not cover: a decision to abandon the place
-- attribute model entirely and reclaim the schema. Read before running.
--
-- IT DESTROYS DATA. Dropping place_photos, place_hours, place_categories and place_slug_history
-- discards every photo reference, every opening hour, the whole taxonomy and every retired URL,
-- which turns old place URLs from 301s back into 404s. Take a dump first:
--
--     pg_dump "$DATABASE_URL" -t places -t place_photos -t place_hours \
--             -t place_categories -t place_slug_history > pre_047_rollback.sql
--
-- Nothing here touches places.id, so reviews, saves and visits are unaffected either way.

BEGIN;

DROP INDEX IF EXISTS idx_places_latlng;
DROP INDEX IF EXISTS idx_places_category;
DROP INDEX IF EXISTS idx_places_dest_price;

ALTER TABLE places DROP CONSTRAINT IF EXISTS places_price_level_range;

ALTER TABLE places DROP COLUMN IF EXISTS category_id;
ALTER TABLE places DROP COLUMN IF EXISTS street_address;
ALTER TABLE places DROP COLUMN IF EXISTS neighborhood;
ALTER TABLE places DROP COLUMN IF EXISTS region;
ALTER TABLE places DROP COLUMN IF EXISTS postal_code;
ALTER TABLE places DROP COLUMN IF EXISTS lat;
ALTER TABLE places DROP COLUMN IF EXISTS lng;
ALTER TABLE places DROP COLUMN IF EXISTS phone;
ALTER TABLE places DROP COLUMN IF EXISTS website_url;
ALTER TABLE places DROP COLUMN IF EXISTS price_level;
ALTER TABLE places DROP COLUMN IF EXISTS timezone;
ALTER TABLE places DROP COLUMN IF EXISTS data_source;
ALTER TABLE places DROP COLUMN IF EXISTS data_source_url;
ALTER TABLE places DROP COLUMN IF EXISTS data_checked_at;

DROP TABLE IF EXISTS place_photos;
DROP TABLE IF EXISTS place_hours;
DROP TABLE IF EXISTS place_slug_history;
DROP TABLE IF EXISTS place_categories;

DELETE FROM schema_migrations WHERE version = '047_place_attributes';

COMMIT;
