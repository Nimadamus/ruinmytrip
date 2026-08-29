-- Migration 055 - neighborhoods become entities instead of a string on a place.
--
-- Today places.neighborhood is free text copied from OpenStreetMap. The current 100 values are
-- clean, so nothing is broken yet -- but the shape of the data guarantees fragmentation as soon as
-- a second source, an editor or a different OSM tag disagrees about wording. "1st Arrondissement"
-- and "Paris 1er Arrondissement" are the same place and would count as two, and no amount of
-- careful template code can merge them after the fact, because the identity was never recorded.
--
-- Three things are added, and nothing existing is removed. places.neighborhood KEEPS its text.
--
--   1. NEIGHBORHOODS. Canonical identity, scoped to a destination -- Altstadt exists in both
--      Munich and Zurich and they are not the same area, so uniqueness is per destination and
--      never global.
--
--   2. NEIGHBORHOOD_ALIASES. Every other legitimate way to write the same area: the local-language
--      form, the English exonym, the administrative wording, the variant a different source emits.
--      Matching happens on alias_key, a normalised form, so accents and case never split an area
--      in two. This is data, not a PHP array: an editor has to be able to add a variant without a
--      deploy.
--
--   3. places.neighborhood_id. NULLABLE ON PURPOSE, and it stays null until identity is certain.
--      An unresolved place keeps its raw text and still displays it. Guessing which area a venue
--      is in, to make a browse module look fuller, is exactly the kind of invention this codebase
--      does not do.
--
-- KIND is the honest part. OSM answers "what area is this in" with whatever administrative unit it
-- holds, so the real data contains "Municipio Roma I", "Municipio 1", "Manhattan" and
-- "Venezia-Murano-Burano" alongside Kreuzberg and Le Marais. A borough is not a neighborhood and a
-- comune is not a neighborhood. Rather than delete those or dress them up, each entity records
-- what it actually is, and only the kinds a traveler would browse by are offered as neighborhoods.

CREATE TABLE IF NOT EXISTS neighborhoods (
    id              SERIAL PRIMARY KEY,
    destination_id  INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    slug            TEXT NOT NULL,
    canonical_name  TEXT NOT NULL,
    -- The name locals use, when it differs from the name we show. Kolonaki is displayed in Latin
    -- script; the neighborhood is still Κολωνάκι and the page should be able to say so.
    local_name      TEXT,
    -- neighborhood | district | borough | administrative. Only the first two are browsable.
    kind            TEXT NOT NULL DEFAULT 'neighborhood',
    lat             NUMERIC(9,6),
    lng             NUMERIC(9,6),
    blurb           TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS neighborhoods_dest_slug ON neighborhoods (destination_id, slug);
CREATE INDEX IF NOT EXISTS neighborhoods_dest_kind ON neighborhoods (destination_id, kind);

CREATE TABLE IF NOT EXISTS neighborhood_aliases (
    id              SERIAL PRIMARY KEY,
    neighborhood_id INTEGER NOT NULL REFERENCES neighborhoods(id) ON DELETE CASCADE,
    destination_id  INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    alias           TEXT NOT NULL,
    alias_key       TEXT NOT NULL,
    -- Where the variant came from, so a bad import can be undone without taking curated ones with it.
    source          TEXT NOT NULL DEFAULT 'curated',
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- One spelling can only mean one area WITHIN a destination. Across destinations it is free to
-- repeat, which is the whole reason the constraint is scoped.
CREATE UNIQUE INDEX IF NOT EXISTS neighborhood_aliases_key ON neighborhood_aliases (destination_id, alias_key);
CREATE INDEX IF NOT EXISTS neighborhood_aliases_nb ON neighborhood_aliases (neighborhood_id);

ALTER TABLE places ADD COLUMN IF NOT EXISTS neighborhood_id INTEGER REFERENCES neighborhoods(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS places_neighborhood_id ON places (neighborhood_id);
