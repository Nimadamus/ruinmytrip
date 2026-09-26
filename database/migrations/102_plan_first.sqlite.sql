-- sqlite mirror of 102_plan_first.pgsql.sql. See that file for why each piece exists.

CREATE TABLE IF NOT EXISTS held_work (
    user_id    INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    payload    TEXT NOT NULL,
    created_at TEXT NOT NULL
);

ALTER TABLE contribution_events ADD COLUMN path TEXT;
ALTER TABLE contribution_events ADD COLUMN detail TEXT;
CREATE INDEX IF NOT EXISTS idx_contribution_events_visitor_event ON contribution_events (visitor, event);
