-- Who said these opening hours.
--
-- Without this there is no way for an importer to refresh what IT wrote while leaving alone what a
-- person typed, and "replace all the hours for this place" is how a provider quietly overrules a
-- human correction.
ALTER TABLE place_hours ADD COLUMN source TEXT;
ALTER TABLE place_hours ADD COLUMN created_at TEXT;
CREATE INDEX IF NOT EXISTS idx_place_hours_source ON place_hours(place_id, source);
