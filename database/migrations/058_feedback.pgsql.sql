-- Migration 058 - one queue for "something here is wrong".
--
-- Three things were missing and they are the same thing wearing three hats: a traveler who spots a
-- closed restaurant, a traveler whose opening hours are out of date, and a traveler who cannot find
-- any way to tell us either. There was a queue for a place we do NOT have (place_suggestions) and
-- no queue at all for a place we have and have wrong.
--
-- ONE TABLE, not two. A correction is feedback about a place; a site problem is feedback about no
-- place. Splitting them would mean two schemas, two admin screens and two sets of rules about who
-- may resolve what, for a difference that is one nullable column wide.
--
-- NOTHING HERE EVER WRITES TO A PLACE. A submission is a message that a person reads and acts on by
-- hand, exactly like a place suggestion. An "is this closed?" form that could close a business
-- automatically is a denial-of-service tool with a friendly label on it, and the same reasoning
-- that stopped reports from hiding reviews applies here without modification.

CREATE TABLE IF NOT EXISTS feedback (
    id              SERIAL PRIMARY KEY,
    -- What kind of wrong. A closed list, because these are counted, filtered and reported on, and
    -- free text would be a different phrase every time.
    kind            TEXT NOT NULL,
    -- Set for a correction, null for a site-wide message.
    place_id        INTEGER REFERENCES places(id) ON DELETE CASCADE,
    message         TEXT NOT NULL,
    -- Optional, and only so we can reply. Never required: making somebody hand over an address to
    -- tell us a museum moved is a good way not to be told.
    contact_email   TEXT,
    reported_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status          TEXT NOT NULL DEFAULT 'pending',   -- pending | resolved | rejected | duplicate
    resolved_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    resolved_at     TIMESTAMP,
    resolution_note TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS feedback_status ON feedback (status, created_at);
CREATE INDEX IF NOT EXISTS feedback_place ON feedback (place_id, status);
