-- Migration 102 - a trip before an account, and the two columns that let us read the funnel.
--
-- held_work: what a new member wrote before their email came back, keyed by the account. It used
-- to live only in the PHP session, and the confirmation link is usually opened from a mail app,
-- which is a different browser with a different session, so the trip they had just written was
-- silently dropped on the one click that was meant to publish it. One row per member; a second
-- submission replaces the first, the same rule the session slot has always had.
--
-- contribution_events.path: the page a signed out visit landed on (a path, never a query string),
-- so "which landing pages produce members" is a query rather than a guess.
-- contribution_events.detail: a short closed-list label, such as which call to action was pressed.

CREATE TABLE IF NOT EXISTS held_work (
    user_id    INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    payload    TEXT      NOT NULL,
    created_at TIMESTAMP NOT NULL
);

ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS path TEXT;
ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS detail TEXT;
CREATE INDEX IF NOT EXISTS idx_contribution_events_visitor_event ON contribution_events (visitor, event);
