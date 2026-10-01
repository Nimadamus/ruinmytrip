-- Migration 105 - signed out match alerts, "I'm going" share cards, and first in a city recognitions.
--
-- match_alerts: an email, a city (optionally an occasion) and a window, from somebody who has not
-- made an account. Double opt in: nothing but the confirmation is ever sent until the link in it is
-- clicked. Only sha256 of the link token is stored, like every other token here. When the address
-- later becomes a confirmed account, the alert is turned into that member's trip and city follow.
--
-- going_cards: the shareable "I'm going" page and image. The dates and city are what the traveler
-- chose to share; the name is on it only if they ticked it.
--
-- recognitions: First traveler / First review for a city (Founding Traveler stays the existing
-- badge). Permanent once earned, revoked only by moderation, never by the member changing their
-- mind about a later trip.
CREATE TABLE IF NOT EXISTS match_alerts (
    id             SERIAL PRIMARY KEY,
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
    id             SERIAL PRIMARY KEY,
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
    id             SERIAL PRIMARY KEY,
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
