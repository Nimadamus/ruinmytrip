-- Places: the thing a review is actually about.
--
-- Until now `reviews.subject_name` was free text, so twenty reviews of the same hotel were twenty
-- unrelated strings: no aggregate rating, no page to land on, nothing for search engines to treat
-- as a reviewed entity. This table gives each hotel/restaurant/attraction/experience one row per
-- destination, and reviews point at it.
--
-- Dedupe key is `name_key` (lowercased, punctuation and articles stripped -- see
-- rmt_place_name_key in app/places.php), UNIQUE per destination and deliberately NOT per type:
-- one traveler filing "Hotel Arts" under `hotel` and another under `attraction` must still land on
-- the same place, otherwise the aggregate splits in two and the whole point is lost. The type on
-- the row is whoever created it first; it is a label, not an identity.
--
-- `subject_name` stays on reviews as written. It is what the author typed and it is what the
-- review renders if the place row is ever hidden -- this migration is additive, nothing is moved.
CREATE TABLE IF NOT EXISTS places (
  id SERIAL PRIMARY KEY,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  slug TEXT UNIQUE NOT NULL,
  name TEXT NOT NULL,
  name_key TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'attraction',
  created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_places_dest_namekey ON places(destination_id, name_key);
CREATE INDEX IF NOT EXISTS idx_places_dest ON places(destination_id, type, id);

ALTER TABLE reviews ADD COLUMN IF NOT EXISTS place_id INTEGER REFERENCES places(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_reviews_place ON reviews(place_id, status);

-- Full-text search, same pattern as migrations 015/019/026. Country and destination name are not
-- copied in: they live on the joined destinations row and duplicating them here would go stale.
ALTER TABLE places ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(name,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(type,'')), 'B')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_places_search ON places USING GIN (search_vector);
