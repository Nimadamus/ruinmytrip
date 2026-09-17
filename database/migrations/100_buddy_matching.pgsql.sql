-- Migration 100 - travel buddies become a matching section.
--
-- A buddy post gains what makes two strangers a match: who is travelling (solo, a couple, friends,
-- a group), what they are into, the ages they would like to travel with, and for a cruise the
-- things that identify one sailing (line, ship, port). A trip gains a trip type so the same filters
-- work on dated trips as on buddy posts. Everything is nullable and additive.
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS travel_party   TEXT;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS interests      TEXT;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS age_min        INTEGER;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS age_max        INTEGER;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS cruise_line    TEXT;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS ship           TEXT;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS departure_port TEXT;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS itinerary      TEXT;
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS ship_key       TEXT;
CREATE INDEX IF NOT EXISTS idx_buddy_posts_sailing ON buddy_posts (ship_key, date_from);
CREATE INDEX IF NOT EXISTS idx_buddy_posts_dest ON buddy_posts (destination_id, date_from);
ALTER TABLE trips ADD COLUMN IF NOT EXISTS trip_type TEXT;

-- A local who said they are open to meeting travelers has no trip to be asked on, so a request to
-- a local is its own row. Same rules as a trip connect: no words, the local decides, and only an
-- accepted request opens a private message.
CREATE TABLE IF NOT EXISTS local_connects (
    from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    state        TEXT    NOT NULL DEFAULT 'interested',
    created_at   TIMESTAMP NOT NULL,
    decided_at   TIMESTAMP,
    PRIMARY KEY (from_user_id, to_user_id)
);
CREATE INDEX IF NOT EXISTS idx_local_connects_to ON local_connects (to_user_id, state);
