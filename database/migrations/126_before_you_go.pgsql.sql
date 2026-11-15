-- Migration 126 - "before you go" updates: an email from somebody reading a tourist tax or a scam
-- warnings page who wants to hear when that city's tax or official warnings change.
--
-- Double opt in, like match_alerts (105): nothing but the confirmation is sent until its link is
-- clicked, and only sha256 of the link token is kept. topic is 'tax' or 'scams'; city is the tax
-- key (app/tourist_tax.php) or the destination slug (app/city_scams.php), '' for every city.
-- byg_state remembers the last content hash per topic and city, so a send happens only on a change.
CREATE TABLE IF NOT EXISTS byg_signups (
    id           SERIAL PRIMARY KEY,
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
