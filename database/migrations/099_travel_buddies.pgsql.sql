-- Migration 099 - travel buddies.
--
-- A trip has one owner and one city. Somebody booking a seven night Caribbean cruise, a road trip
-- or a festival weekend who wants company has nothing to post: the ship is not a destination and
-- the person does not exist yet. A buddy post is that ask, and buddy_interest is who put a hand up.
-- The poster accepts people one at a time, and acceptance is what opens a private message.
CREATE TABLE IF NOT EXISTS buddy_posts (
    id             SERIAL PRIMARY KEY,
    user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    trip_type      TEXT    NOT NULL,
    title          TEXT    NOT NULL,
    where_text     TEXT    NOT NULL,
    destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
    date_from      DATE    NOT NULL,
    date_to        DATE    NOT NULL,
    flexible       INTEGER NOT NULL DEFAULT 0,
    spots          INTEGER NOT NULL DEFAULT 1,
    budget         TEXT    NOT NULL DEFAULT 'any',
    description    TEXT    NOT NULL,
    status         TEXT    NOT NULL DEFAULT 'open',
    created_at     TIMESTAMP NOT NULL,
    updated_at     TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_buddy_posts_open ON buddy_posts (status, date_to);
CREATE INDEX IF NOT EXISTS idx_buddy_posts_user ON buddy_posts (user_id);

CREATE TABLE IF NOT EXISTS buddy_interest (
    post_id    INTEGER NOT NULL REFERENCES buddy_posts(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    note       TEXT,
    state      TEXT    NOT NULL DEFAULT 'interested',
    created_at TIMESTAMP NOT NULL,
    decided_at TIMESTAMP,
    PRIMARY KEY (post_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_buddy_interest_user ON buddy_interest (user_id, state);
