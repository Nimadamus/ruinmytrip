-- sqlite mirror of 052_contribution_events.pgsql.sql. See that file for what this table
-- deliberately does not hold: no user id, no address, no review text.

CREATE TABLE IF NOT EXISTS contribution_events (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  event          TEXT NOT NULL,
  source         TEXT,
  journey        TEXT,
  place_id       INTEGER,
  destination_id INTEGER,
  is_authed      INTEGER NOT NULL DEFAULT 0,
  reason         TEXT,
  created_at     TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_contribution_events_event ON contribution_events(event, created_at);
CREATE INDEX IF NOT EXISTS idx_contribution_events_journey ON contribution_events(journey);
CREATE INDEX IF NOT EXISTS idx_contribution_events_source ON contribution_events(source, event);
