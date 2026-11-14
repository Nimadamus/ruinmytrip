-- Migration 121 - check-ins, city rooms, buddy trip completion. See the pgsql file.
CREATE TABLE IF NOT EXISTS active_checkins (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    destination_id   INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    neighborhood_id  INTEGER REFERENCES neighborhoods(id) ON DELETE SET NULL,
    note             TEXT,
    created_at       TEXT NOT NULL,
    expires_at       TEXT NOT NULL,
    ended_at         TEXT,
    end_reason       TEXT CHECK (end_reason IN ('expired', 'checked_out', 'moved', 'moderated')),
    CHECK (expires_at > created_at)
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_active_checkins_one_open
    ON active_checkins (user_id) WHERE ended_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_active_checkins_city_live
    ON active_checkins (destination_id, expires_at) WHERE ended_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_active_checkins_sweep
    ON active_checkins (expires_at) WHERE ended_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_active_checkins_overlap
    ON active_checkins (destination_id, created_at, expires_at);

CREATE TABLE IF NOT EXISTS city_rooms (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    destination_id  INTEGER NOT NULL UNIQUE REFERENCES destinations(id) ON DELETE CASCADE,
    created_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS city_room_messages (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id     INTEGER NOT NULL REFERENCES city_rooms(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    checkin_id  INTEGER NOT NULL REFERENCES active_checkins(id) ON DELETE CASCADE,
    body        TEXT    NOT NULL,
    created_at  TEXT    NOT NULL,
    hidden_at   TEXT
);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_room ON city_room_messages (room_id, id);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_checkin ON city_room_messages (checkin_id);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_user ON city_room_messages (user_id);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_age ON city_room_messages (created_at);

ALTER TABLE buddy_posts ADD COLUMN completed_at TEXT;
CREATE INDEX IF NOT EXISTS idx_buddy_posts_to_complete ON buddy_posts (date_to) WHERE completed_at IS NULL;
