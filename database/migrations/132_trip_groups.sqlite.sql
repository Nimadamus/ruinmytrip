-- Migration 132 - group trip link. See the pgsql file for the reasoning.
ALTER TABLE trips ADD COLUMN group_code TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS idx_trips_group_code ON trips (group_code) WHERE group_code IS NOT NULL;

CREATE TABLE IF NOT EXISTS trip_guests (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id    INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
    name       TEXT NOT NULL,
    key_hash   TEXT NOT NULL UNIQUE,
    user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_trip_guests_trip ON trip_guests (trip_id);

CREATE TABLE IF NOT EXISTS trip_checklist_claims (
    trip_id    INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
    item_key   TEXT NOT NULL,
    who        TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (trip_id, item_key)
);

CREATE TABLE IF NOT EXISTS trip_date_options (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id    INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
    date_from  TEXT NOT NULL,
    date_to    TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_trip_date_options_trip ON trip_date_options (trip_id);

CREATE TABLE IF NOT EXISTS trip_date_votes (
    option_id  INTEGER NOT NULL REFERENCES trip_date_options(id) ON DELETE CASCADE,
    voter      TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (option_id, voter)
);
