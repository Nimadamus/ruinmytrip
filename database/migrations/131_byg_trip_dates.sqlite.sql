-- Migration 131 - before you go reminders. See the pgsql file.
ALTER TABLE byg_signups ADD COLUMN travel_date TEXT;
ALTER TABLE byg_signups ADD COLUMN page TEXT;
ALTER TABLE byg_signups ADD COLUMN reminded_14 TEXT;
ALTER TABLE byg_signups ADD COLUMN reminded_3 TEXT;
CREATE INDEX IF NOT EXISTS idx_byg_signups_travel ON byg_signups (status, travel_date);
