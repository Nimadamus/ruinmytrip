-- Migration 100 - travel buddies become a matching section. See the pgsql file.
ALTER TABLE buddy_posts ADD COLUMN travel_party   TEXT;
ALTER TABLE buddy_posts ADD COLUMN interests      TEXT;
ALTER TABLE buddy_posts ADD COLUMN age_min        INTEGER;
ALTER TABLE buddy_posts ADD COLUMN age_max        INTEGER;
ALTER TABLE buddy_posts ADD COLUMN cruise_line    TEXT;
ALTER TABLE buddy_posts ADD COLUMN ship           TEXT;
ALTER TABLE buddy_posts ADD COLUMN departure_port TEXT;
ALTER TABLE buddy_posts ADD COLUMN itinerary      TEXT;
ALTER TABLE buddy_posts ADD COLUMN ship_key       TEXT;
CREATE INDEX IF NOT EXISTS idx_buddy_posts_sailing ON buddy_posts (ship_key, date_from);
CREATE INDEX IF NOT EXISTS idx_buddy_posts_dest ON buddy_posts (destination_id, date_from);
ALTER TABLE trips ADD COLUMN trip_type TEXT;

CREATE TABLE IF NOT EXISTS local_connects (
    from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    state        TEXT    NOT NULL DEFAULT 'interested',
    created_at   TEXT    NOT NULL,
    decided_at   TEXT,
    PRIMARY KEY (from_user_id, to_user_id)
);
CREATE INDEX IF NOT EXISTS idx_local_connects_to ON local_connects (to_user_id, state);
