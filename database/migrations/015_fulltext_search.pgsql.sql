ALTER TABLE destinations ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(name,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(country,'')), 'B') ||
    setweight(to_tsvector('english', coalesce(summary,'')), 'C')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_destinations_search ON destinations USING GIN (search_vector);

ALTER TABLE reviews ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(subject_name,'')), 'B') ||
    setweight(to_tsvector('english', coalesce(body,'')), 'C')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_reviews_search ON reviews USING GIN (search_vector);

ALTER TABLE trips ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(body,'')), 'B')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_trips_search ON trips USING GIN (search_vector);

ALTER TABLE guides ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(summary,'')), 'B') ||
    setweight(to_tsvector('english', coalesce(body,'')), 'C')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_guides_search ON guides USING GIN (search_vector);
