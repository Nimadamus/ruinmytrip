-- A local says whether they are open to meeting travelers. Opt in, and off by default.
--
-- The site knows where members live (profiles.home_destination_id, migration 070) and shows them as
-- locals on a city page, which is useful and is also the limit of what living somewhere implies. A
-- traveler wanting to ask a local something, or meet one, is a different request, and the only
-- honest way to answer it is to let people say yes first.
--
-- NOT NULL DEFAULT 0: silence is no, permanently, for everybody who already had a profile.
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS open_to_meeting SMALLINT NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_profiles_open_to_meeting ON profiles (home_destination_id, open_to_meeting);
