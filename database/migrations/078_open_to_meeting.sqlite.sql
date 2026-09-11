-- A local says whether they are open to meeting travelers. Opt in, and off by default.
-- See the pgsql file for why.
ALTER TABLE profiles ADD COLUMN open_to_meeting INTEGER NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_profiles_open_to_meeting ON profiles (home_destination_id, open_to_meeting);
