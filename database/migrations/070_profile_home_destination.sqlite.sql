-- See the pgsql version for why this exists. SQLite has no ADD COLUMN IF NOT EXISTS; the migrator
-- runs each file once, so a plain ADD COLUMN is correct here.
ALTER TABLE profiles ADD COLUMN home_destination_id INTEGER REFERENCES destinations(id);
CREATE INDEX IF NOT EXISTS idx_profiles_home_destination ON profiles (home_destination_id);

-- Same backfill. SQLite has no split_part, so the leading segment is cut by hand.
UPDATE profiles SET home_destination_id = (
  SELECT d.id FROM destinations d
   WHERE LOWER(d.name) = LOWER(TRIM(
     CASE WHEN INSTR(profiles.home_city, ',') > 0
          THEN SUBSTR(profiles.home_city, 1, INSTR(profiles.home_city, ',') - 1)
          ELSE profiles.home_city END))
) WHERE home_destination_id IS NULL AND home_city IS NOT NULL;
