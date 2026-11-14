-- Migration 125 - seeded cities.
--
-- A city added in bulk to widen coverage, rather than built by hand, is marked with the moment it
-- was seeded. Only those cities are held to the content threshold in app/hubs.php (noindex and out
-- of public lists until a trip, a check-in or three sourced warnings). Every city that existed
-- before this has NULL here and keeps the index rule it always had.
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS seeded_at TIMESTAMP;
CREATE INDEX IF NOT EXISTS idx_destinations_seeded ON destinations (seeded_at) WHERE seeded_at IS NOT NULL;
