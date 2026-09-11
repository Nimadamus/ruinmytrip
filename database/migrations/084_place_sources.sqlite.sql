-- Real place data, from a named provider, with a way back to the exact record it came from.
--
-- `data_source` already said WHICH provider. `source_ref` says WHICH RECORD, which is the thing
-- that makes a second import an update rather than a duplicate. The unique index is the real guard:
-- two runs of the same importer, or two importers racing, cannot produce two rows for one museum.
ALTER TABLE places ADD COLUMN source_ref TEXT;
ALTER TABLE places ADD COLUMN source_updated_at TEXT;

CREATE UNIQUE INDEX IF NOT EXISTS places_source_ref_uniq
    ON places(data_source, source_ref)
    WHERE data_source IS NOT NULL AND source_ref IS NOT NULL;

-- The other names a place goes by: "Museu Geologico", "Geological Museum", an old name, a local
-- spelling. Matching an external spelling to a row that already exists is the whole of
-- deduplication, and a directory without aliases grows a second Bellagio every import.
CREATE TABLE IF NOT EXISTS place_aliases (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    place_id    INTEGER NOT NULL,
    alias       TEXT NOT NULL,
    alias_key   TEXT NOT NULL,
    source      TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS place_aliases_uniq ON place_aliases(place_id, alias_key);
CREATE INDEX IF NOT EXISTS place_aliases_key_idx ON place_aliases(alias_key);
