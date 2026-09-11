-- Real place data, from a named provider, with a way back to the exact record it came from.
--
-- `data_source` already said WHICH provider. `source_ref` says WHICH RECORD, which is the thing
-- that makes a second import an update rather than a duplicate. The unique index is the real guard:
-- two runs of the same importer, or two importers racing, cannot produce two rows for one museum.
ALTER TABLE places ADD COLUMN IF NOT EXISTS source_ref TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS source_updated_at TIMESTAMP;

CREATE UNIQUE INDEX IF NOT EXISTS places_source_ref_uniq
    ON places(data_source, source_ref)
    WHERE data_source IS NOT NULL AND source_ref IS NOT NULL;

-- The other names a place goes by: "Museu Geologico", "Geological Museum", an old name, a local
-- spelling. Matching an external spelling to a row that already exists is the whole of
-- deduplication, and a directory without aliases grows a second Bellagio every import.
CREATE TABLE IF NOT EXISTS place_aliases (
    id          SERIAL PRIMARY KEY,
    place_id    INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
    alias       TEXT NOT NULL,
    alias_key   TEXT NOT NULL,
    source      TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS place_aliases_uniq ON place_aliases(place_id, alias_key);
CREATE INDEX IF NOT EXISTS place_aliases_key_idx ON place_aliases(alias_key);
