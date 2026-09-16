-- sqlite mirror of 090_event_visitor.pgsql.sql. See that file for what this token is and, more to
-- the point, what it deliberately is not.

ALTER TABLE contribution_events ADD COLUMN visitor TEXT;
CREATE INDEX IF NOT EXISTS idx_contribution_events_visitor ON contribution_events(visitor, created_at);
