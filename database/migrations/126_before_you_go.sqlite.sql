-- Migration 126 - before you go updates. See the pgsql file for the reasoning.
CREATE TABLE IF NOT EXISTS byg_signups (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    email        TEXT NOT NULL,
    topic        TEXT NOT NULL,
    city         TEXT NOT NULL DEFAULT '',
    city_name    TEXT NOT NULL DEFAULT '',
    status       TEXT NOT NULL DEFAULT 'pending',
    token_hash   TEXT NOT NULL UNIQUE,
    source       TEXT,
    visitor      TEXT,
    created_at   TEXT NOT NULL,
    confirmed_at TEXT,
    notified_at  TEXT,
    notify_count INTEGER NOT NULL DEFAULT 0,
    acq_source   TEXT,
    acq_medium   TEXT,
    acq_campaign TEXT,
    acq_content  TEXT
);
CREATE INDEX IF NOT EXISTS idx_byg_signups_city ON byg_signups (topic, city, status);
CREATE INDEX IF NOT EXISTS idx_byg_signups_email ON byg_signups (email);

CREATE TABLE IF NOT EXISTS byg_state (
    topic      TEXT NOT NULL,
    city       TEXT NOT NULL,
    hash       TEXT NOT NULL,
    changed_at TEXT NOT NULL,
    PRIMARY KEY (topic, city)
);
