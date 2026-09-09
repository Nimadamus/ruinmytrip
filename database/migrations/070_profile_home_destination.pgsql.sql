-- Locals: which city on this site a member actually lives in.
--
-- profiles.home_city has always been free text ("Lisbon, PT", "lisbon", "Lisboa"), which is fine
-- for a profile line and useless for the question the city pages need to answer: who lives here.
-- The text stays exactly as written, because it is the member's own words and some of them live
-- somewhere this site has no page for. This column is the machine-readable half.
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS home_destination_id INTEGER
    REFERENCES destinations(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_profiles_home_destination ON profiles (home_destination_id);

-- Backfill from what people already typed. Same conservative rule the PHP resolver uses: an exact
-- name match on the text, or on the part before the first comma. Anything fuzzier would list
-- somebody as a resident of a city they merely mentioned.
UPDATE profiles p SET home_destination_id = d.id
  FROM destinations d
 WHERE p.home_destination_id IS NULL
   AND p.home_city IS NOT NULL
   AND LOWER(TRIM(split_part(p.home_city, ',', 1))) = LOWER(d.name);
