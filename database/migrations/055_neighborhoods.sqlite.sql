-- Migration 055 (sqlite) - see the pgsql file for why. Same shape, same constraints.
--
-- The types are chosen to behave the same way on both drivers rather than to look idiomatic on
-- either: this codebase has been bitten three times by a column that meant one thing in SQLite and
-- another in Postgres, and every one of those passed a green test suite before failing live.

CREATE TABLE IF NOT EXISTS neighborhoods (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    destination_id  INTEGER NOT NULL,
    slug            TEXT NOT NULL,
    canonical_name  TEXT NOT NULL,
    local_name      TEXT,
    kind            TEXT NOT NULL DEFAULT 'neighborhood',
    lat             NUMERIC(9,6),
    lng             NUMERIC(9,6),
    blurb           TEXT,
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS neighborhoods_dest_slug ON neighborhoods (destination_id, slug);
CREATE INDEX IF NOT EXISTS neighborhoods_dest_kind ON neighborhoods (destination_id, kind);

CREATE TABLE IF NOT EXISTS neighborhood_aliases (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    neighborhood_id INTEGER NOT NULL,
    destination_id  INTEGER NOT NULL,
    alias           TEXT NOT NULL,
    alias_key       TEXT NOT NULL,
    source          TEXT NOT NULL DEFAULT 'curated',
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS neighborhood_aliases_key ON neighborhood_aliases (destination_id, alias_key);
CREATE INDEX IF NOT EXISTS neighborhood_aliases_nb ON neighborhood_aliases (neighborhood_id);

ALTER TABLE places ADD COLUMN neighborhood_id INTEGER;
CREATE INDEX IF NOT EXISTS places_neighborhood_id ON places (neighborhood_id);
