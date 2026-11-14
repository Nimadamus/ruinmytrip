-- Migration 121 - "right now" check-ins, city rooms, and the moment a buddy trip is over.
--
-- A check-in is "I am in this city until <time>". It is live while ended_at IS NULL AND
-- expires_at > now, and nothing reads a check-in without that test, so a traveler who has left
-- drops out of every count and room at the instant of expiry even when the sweep job runs late.
-- The sweep (GET /cron/checkins, app/checkins.php) only stamps ended_at and prunes old messages.
--
-- Room membership is deliberately not stored. You are in a city's room exactly while you hold a
-- live check-in there, so there is no member list to fall out of date.
--
-- Timestamps are written by PHP (date('Y-m-d H:i:s')), the same clock every other table here
-- uses, and compared against a PHP "now". Never against the database's NOW(): the two clocks are
-- not promised to share a time zone.
CREATE TABLE IF NOT EXISTS active_checkins (
    id               SERIAL PRIMARY KEY,
    user_id          INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    destination_id   INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    neighborhood_id  INTEGER REFERENCES neighborhoods(id) ON DELETE SET NULL,
    note             TEXT,
    created_at       TIMESTAMP NOT NULL,
    expires_at       TIMESTAMP NOT NULL,
    ended_at         TIMESTAMP,
    end_reason       TEXT CHECK (end_reason IN ('expired', 'checked_out', 'moved', 'moderated')),
    CHECK (expires_at > created_at)
);
-- One open check-in per person. Checking in somewhere else closes the old row ('moved') first.
CREATE UNIQUE INDEX IF NOT EXISTS uq_active_checkins_one_open
    ON active_checkins (user_id) WHERE ended_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_active_checkins_city_live
    ON active_checkins (destination_id, expires_at) WHERE ended_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_active_checkins_sweep
    ON active_checkins (expires_at) WHERE ended_at IS NULL;
-- Who overlapped whom in a city, for the review window that opens after a check-in ends.
CREATE INDEX IF NOT EXISTS idx_active_checkins_overlap
    ON active_checkins (destination_id, created_at, expires_at);

CREATE TABLE IF NOT EXISTS city_rooms (
    id              SERIAL PRIMARY KEY,
    destination_id  INTEGER NOT NULL UNIQUE REFERENCES destinations(id) ON DELETE CASCADE,
    created_at      TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS city_room_messages (
    id          BIGSERIAL PRIMARY KEY,
    room_id     INTEGER NOT NULL REFERENCES city_rooms(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    -- The check-in that entitled the author to post. The feed shows a message only while it is live.
    checkin_id  INTEGER NOT NULL REFERENCES active_checkins(id) ON DELETE CASCADE,
    body        TEXT    NOT NULL,
    created_at  TIMESTAMP NOT NULL,
    hidden_at   TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_room ON city_room_messages (room_id, id);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_checkin ON city_room_messages (checkin_id);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_user ON city_room_messages (user_id);
CREATE INDEX IF NOT EXISTS idx_city_room_messages_age ON city_room_messages (created_at);

-- Stamped once a buddy trip's last day has passed (or its owner marks it done). Nothing reads it
-- yet; it is the fact the private vouch / flag step needs: "this trip is over, as of when".
ALTER TABLE buddy_posts ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP;
CREATE INDEX IF NOT EXISTS idx_buddy_posts_to_complete ON buddy_posts (date_to) WHERE completed_at IS NULL;
