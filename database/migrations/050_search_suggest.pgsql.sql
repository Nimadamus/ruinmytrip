-- Migration 050 - what autocomplete needs that full-text search cannot give it.
--
-- /search already runs tsvector + ts_rank across seven entity types and it works. It cannot do
-- autocomplete: plainto_tsquery lexes whole words, so "Bell" matches nothing at all and "Rijks"
-- matches nothing at all. Typeahead is a prefix problem wearing a search problem's clothes.
--
-- Three things are added, and nothing existing changes:
--
--   1. A NORMALISED NAME COLUMN per searchable entity. Matching happens on it; display always
--      comes from the real name. "Cafe Savoy" has to find "Café Savoy" because nobody types the
--      accent, and "Munchen" has to find "München" for the same reason. Normalisation is for
--      matching only -- the canonical name is never touched.
--   2. AN ALIAS TABLE. Vienna is Wien, Prague is Praha, Athens is Athina, and NYC is New York
--      City. Those are data, not a PHP array: they need to be addable by an editor, countable,
--      and joinable, and hardcoding a hundred of them into application logic is how a lookup
--      table gets written by accident.
--   3. A SEARCH LOG. What people type and find nothing for is the most direct statement of what
--      to build next. It records the normalised query, the result count and, when it happens, what
--      was clicked and where in the list. No user id, no IP, no session: an analytics table does
--      not need to know who, and one that does not hold it cannot leak it.
--
-- pg_trgm is attempted for typo tolerance and its failure is survivable: the extension needs
-- privileges that a managed database may not grant, the application detects at runtime whether
-- similarity() exists, and falls back to prefix and substring matching when it does not. A deploy
-- must not fail over a nice-to-have.

ALTER TABLE places       ADD COLUMN IF NOT EXISTS name_norm TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS name_norm TEXT;

CREATE TABLE IF NOT EXISTS search_aliases (
  id          SERIAL PRIMARY KEY,
  entity_type TEXT NOT NULL,           -- 'destination' | 'place'
  entity_id   INTEGER NOT NULL,
  alias       TEXT NOT NULL,           -- as a person would write it
  alias_norm  TEXT NOT NULL,           -- as it is matched
  source      TEXT,                    -- 'local_name' | 'abbreviation' | 'editorial'
  created_at  TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_search_aliases_unique ON search_aliases(entity_type, entity_id, alias_norm);
CREATE INDEX IF NOT EXISTS idx_search_aliases_norm ON search_aliases(alias_norm);

CREATE TABLE IF NOT EXISTS search_log (
  id               SERIAL PRIMARY KEY,
  query_norm       TEXT NOT NULL,
  result_count     INTEGER NOT NULL,
  clicked_type     TEXT,
  clicked_id       INTEGER,
  clicked_position INTEGER,
  created_at       TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_search_log_query ON search_log(query_norm, created_at);
-- The zero-result report reads this one: "what did people ask for that we had nothing for".
CREATE INDEX IF NOT EXISTS idx_search_log_empty ON search_log(result_count, created_at);

-- Prefix matching. text_pattern_ops is what makes LIKE 'rijks%' an index range scan instead of a
-- sequential scan of every place on the site; the default opclass will not serve a LIKE prefix
-- unless the database happens to be in the C locale.
CREATE INDEX IF NOT EXISTS idx_places_name_norm_prefix       ON places(name_norm text_pattern_ops);
CREATE INDEX IF NOT EXISTS idx_destinations_name_norm_prefix ON destinations(name_norm text_pattern_ops);
CREATE INDEX IF NOT EXISTS idx_search_aliases_prefix         ON search_aliases(alias_norm text_pattern_ops);

-- Typo tolerance, best effort. Wrapped so a database that will not grant the extension still
-- migrates: the application asks at runtime whether similarity() exists and does without it.
DO $do$ BEGIN
  BEGIN
    CREATE EXTENSION IF NOT EXISTS pg_trgm;
  EXCEPTION WHEN OTHERS THEN
    RAISE NOTICE 'pg_trgm unavailable (%); autocomplete will run without typo tolerance', SQLERRM;
  END;
END $do$;

DO $do$ BEGIN
  IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm') THEN
    CREATE INDEX IF NOT EXISTS idx_places_name_trgm       ON places       USING gin (name_norm gin_trgm_ops);
    CREATE INDEX IF NOT EXISTS idx_destinations_name_trgm ON destinations USING gin (name_norm gin_trgm_ops);
    CREATE INDEX IF NOT EXISTS idx_aliases_name_trgm      ON search_aliases USING gin (alias_norm gin_trgm_ops);
  END IF;
END $do$;
