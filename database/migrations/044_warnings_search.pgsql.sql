-- Put warnings and the editorial landing pages into full-text search.
-- Same weighting convention as migration 015: A = title, B = the named thing (place/business),
-- C = the prose. Searching "airport taxi scam istanbul" has to reach a warning body, not just
-- destination names.

ALTER TABLE warnings ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(provider_name,'') || ' ' || coalesce(location_detail,'')), 'B') ||
    setweight(to_tsvector('english', coalesce(body,'') || ' ' || coalesce(advice,'')), 'C')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_warnings_search ON warnings USING GIN (search_vector);

ALTER TABLE seo_landing_pages ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(h1,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(meta_description,'')), 'B') ||
    setweight(to_tsvector('english', coalesce(intro,'') || ' ' || coalesce(body,'')), 'C')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_seo_pages_search ON seo_landing_pages USING GIN (search_vector);

-- Trigram index on destination names powers typo-tolerant autocomplete ("barcelna" -> Barcelona).
-- pg_trgm ships with Postgres; if the extension cannot be created the index is skipped and
-- app/search falls back to prefix matching, so this is not load-bearing.
DO $$
BEGIN
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
  CREATE INDEX IF NOT EXISTS idx_destinations_name_trgm ON destinations USING GIN (name gin_trgm_ops);
  CREATE INDEX IF NOT EXISTS idx_destinations_country_trgm ON destinations USING GIN (country gin_trgm_ops);
EXCEPTION WHEN OTHERS THEN
  RAISE NOTICE 'pg_trgm unavailable; autocomplete falls back to prefix matching';
END $$;
