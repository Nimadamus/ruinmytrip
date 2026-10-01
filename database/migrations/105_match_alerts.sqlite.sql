-- Migration 105 - match alerts, going cards, recognitions. See the pgsql file for the reasoning.
CREATE TABLE IF NOT EXISTS match_alerts (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    email          TEXT NOT NULL,
    destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    occasion       TEXT,
    date_from      TEXT NOT NULL,
    date_to        TEXT NOT NULL,
    flex_days      INTEGER NOT NULL DEFAULT 0,
    status         TEXT NOT NULL DEFAULT 'pending',
    token_hash     TEXT NOT NULL UNIQUE,
    user_id        INTEGER REFERENCES users(id) ON DELETE SET NULL,
    visitor        TEXT,
    card_code      TEXT,
    created_at     TEXT NOT NULL,
    confirmed_at   TEXT,
    notified_at    TEXT,
    notify_count   INTEGER NOT NULL DEFAULT 0,
    converted_at   TEXT
);
CREATE INDEX IF NOT EXISTS idx_match_alerts_dest ON match_alerts (destination_id, status);
CREATE INDEX IF NOT EXISTS idx_match_alerts_email ON match_alerts (email);

CREATE TABLE IF NOT EXISTS going_cards (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    code           TEXT NOT NULL UNIQUE,
    user_id        INTEGER REFERENCES users(id) ON DELETE CASCADE,
    alert_id       INTEGER REFERENCES match_alerts(id) ON DELETE CASCADE,
    destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    occasion       TEXT,
    date_from      TEXT NOT NULL,
    date_to        TEXT NOT NULL,
    show_name      INTEGER NOT NULL DEFAULT 0,
    status         TEXT NOT NULL DEFAULT 'live',
    visits         INTEGER NOT NULL DEFAULT 0,
    created_at     TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_going_cards_user ON going_cards (user_id);

CREATE TABLE IF NOT EXISTS recognitions (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    kind           TEXT NOT NULL,
    destination_id INTEGER REFERENCES destinations(id) ON DELETE CASCADE,
    ordinal        INTEGER,
    ref_type       TEXT,
    ref_id         INTEGER,
    earned_at      TEXT NOT NULL,
    revoked_at     TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_recognitions_city ON recognitions (kind, destination_id) WHERE destination_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_recognitions_founding ON recognitions (user_id, kind) WHERE destination_id IS NULL;
