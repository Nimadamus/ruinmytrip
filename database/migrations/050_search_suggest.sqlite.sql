-- sqlite mirror of 050_search_suggest.pgsql.sql. See that file for the rationale.
--
-- Two differences, both because SQLite has neither the opclass nor the extension:
--
--   * a plain index on name_norm already serves LIKE 'rijks%' here, because SQLite uses an index
--     for a prefix LIKE when the column is TEXT and the pattern has no leading wildcard;
--   * there is no pg_trgm, so typo tolerance falls back to substring matching. The application
--     asks the driver what it can do rather than assuming, so local and production differ in
--     result quality and never in behaviour.

ALTER TABLE places       ADD COLUMN name_norm TEXT;
ALTER TABLE destinations ADD COLUMN name_norm TEXT;

CREATE TABLE IF NOT EXISTS search_aliases (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  entity_type TEXT NOT NULL,
  entity_id   INTEGER NOT NULL,
  alias       TEXT NOT NULL,
  alias_norm  TEXT NOT NULL,
  source      TEXT,
  created_at  TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_search_aliases_unique ON search_aliases(entity_type, entity_id, alias_norm);
CREATE INDEX IF NOT EXISTS idx_search_aliases_norm ON search_aliases(alias_norm);

CREATE TABLE IF NOT EXISTS search_log (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  query_norm       TEXT NOT NULL,
  result_count     INTEGER NOT NULL,
  clicked_type     TEXT,
  clicked_id       INTEGER,
  clicked_position INTEGER,
  created_at       TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_search_log_query ON search_log(query_norm, created_at);
CREATE INDEX IF NOT EXISTS idx_search_log_empty ON search_log(result_count, created_at);

CREATE INDEX IF NOT EXISTS idx_places_name_norm       ON places(name_norm);
CREATE INDEX IF NOT EXISTS idx_destinations_name_norm ON destinations(name_norm);
