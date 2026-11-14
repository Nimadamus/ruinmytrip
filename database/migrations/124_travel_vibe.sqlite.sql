-- Migration 124 - travel vibe: budget and pace. See the pgsql file.
ALTER TABLE profiles ADD COLUMN budget_level TEXT;      -- budget | mid | luxury
ALTER TABLE profiles ADD COLUMN travel_pace TEXT;       -- packed | flexible | slow
ALTER TABLE buddy_posts ADD COLUMN travel_pace TEXT;    -- the pace of this trip
CREATE INDEX IF NOT EXISTS idx_profiles_vibe ON profiles (budget_level, travel_pace);
