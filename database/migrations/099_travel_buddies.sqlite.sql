-- Migration 099 - travel buddies. See the pgsql file.
CREATE TABLE IF NOT EXISTS buddy_posts (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    trip_type      TEXT    NOT NULL,
    title          TEXT    NOT NULL,
    where_text     TEXT    NOT NULL,
    destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
    date_from      TEXT    NOT NULL,
    date_to        TEXT    NOT NULL,
    flexible       INTEGER NOT NULL DEFAULT 0,
    spots          INTEGER NOT NULL DEFAULT 1,
    budget         TEXT    NOT NULL DEFAULT 'any',
    description    TEXT    NOT NULL,
    status         TEXT    NOT NULL DEFAULT 'open',
    created_at     TEXT    NOT NULL,
    updated_at     TEXT
);
CREATE INDEX IF NOT EXISTS idx_buddy_posts_open ON buddy_posts (status, date_to);
CREATE INDEX IF NOT EXISTS idx_buddy_posts_user ON buddy_posts (user_id);

CREATE TABLE IF NOT EXISTS buddy_interest (
    post_id    INTEGER NOT NULL REFERENCES buddy_posts(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    note       TEXT,
    state      TEXT    NOT NULL DEFAULT 'interested',
    created_at TEXT    NOT NULL,
    decided_at TEXT,
    PRIMARY KEY (post_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_buddy_interest_user ON buddy_interest (user_id, state);
