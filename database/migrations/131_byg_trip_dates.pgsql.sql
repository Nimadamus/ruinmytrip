-- Migration 131 - "before you go" reminders (2026-11-23). A signup can carry the date the trip
-- starts and the page it came from: the first email sends that page's answer, and confirmed signups
-- get one reminder 14 days and one 3 days before the date. Each reminder is stamped once.
ALTER TABLE byg_signups ADD COLUMN IF NOT EXISTS travel_date TEXT;
ALTER TABLE byg_signups ADD COLUMN IF NOT EXISTS page TEXT;
ALTER TABLE byg_signups ADD COLUMN IF NOT EXISTS reminded_14 TEXT;
ALTER TABLE byg_signups ADD COLUMN IF NOT EXISTS reminded_3 TEXT;
CREATE INDEX IF NOT EXISTS idx_byg_signups_travel ON byg_signups (status, travel_date);
