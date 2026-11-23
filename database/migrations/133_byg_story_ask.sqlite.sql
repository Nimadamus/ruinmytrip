-- Migration 133 - after the trip. See the pgsql file.
ALTER TABLE byg_signups ADD COLUMN story_asked TEXT;
