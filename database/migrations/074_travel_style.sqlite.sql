-- See the pgsql version for why this is a closed list.
-- How somebody travels, in their own words from a short list.
--
-- "Solo travelers" is the one discovery filter this site could not answer: it knew who was going
-- where and when, and nothing about who they would be arriving with. Somebody travelling alone is
-- looking for different company from a family of four, and both of them were shown the same list.
--
-- Deliberately a closed list rather than free text, because this is something the site groups
-- people by and a free-text field is a filter nobody can build. Null means they have not said,
-- which is most people, and "not said" is never displayed as an answer.
ALTER TABLE profiles ADD COLUMN travel_style TEXT;
CREATE INDEX IF NOT EXISTS idx_profiles_travel_style ON profiles (travel_style);
