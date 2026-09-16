-- sqlite mirror of 093_event_cookied.pgsql.sql. See that file for what one returned cookie proves
-- and, more importantly, what a missing one does not.

ALTER TABLE contribution_events ADD COLUMN cookied INTEGER;
CREATE INDEX IF NOT EXISTS idx_contribution_events_cookied ON contribution_events (cookied, created_at);
