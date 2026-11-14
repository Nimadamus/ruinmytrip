-- Migration 124 - travel vibe: budget and pace.
--
-- What somebody is into is already here (profile_interests, buddy_posts.interests) and so is who
-- they travel with (travel_style) and a post's budget. The two things missing for "would we
-- actually get along on the road" are a member's own usual budget, and pace: packed days or slow
-- ones. Both optional, self-declared, shown on the profile only when set (app/vibe.php).
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS budget_level TEXT;      -- budget | mid | luxury
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS travel_pace TEXT;       -- packed | flexible | slow
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS travel_pace TEXT;    -- the pace of this trip
CREATE INDEX IF NOT EXISTS idx_profiles_vibe ON profiles (budget_level, travel_pace);
