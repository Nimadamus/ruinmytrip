-- sqlite mirror of 049_review_aspects.pgsql.sql. See that file for the rationale.
--
-- The traveler_type allowed-set is a CHECK constraint on Postgres and is enforced in PHP
-- (rmt_traveler_type_clean) on the single path that writes it, because SQLite cannot add a CHECK
-- in ALTER TABLE. Same arrangement as places.price_level in 047.

CREATE TABLE IF NOT EXISTS review_ratings (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  review_id INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
  aspect    TEXT NOT NULL,
  value     INTEGER NOT NULL,
  CONSTRAINT review_ratings_value_range CHECK (value >= 1 AND value <= 5)
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_review_ratings_unique ON review_ratings(review_id, aspect);
CREATE INDEX IF NOT EXISTS idx_review_ratings_aspect ON review_ratings(aspect, value);

ALTER TABLE reviews ADD COLUMN traveler_type TEXT;
CREATE INDEX IF NOT EXISTS idx_reviews_traveler_type ON reviews(traveler_type) WHERE traveler_type IS NOT NULL;

INSERT OR IGNORE INTO review_ratings (review_id, aspect, value)
SELECT id, 'safety', safety_rating FROM reviews WHERE safety_rating IS NOT NULL;

INSERT OR IGNORE INTO review_ratings (review_id, aspect, value)
SELECT id, 'value', value_rating FROM reviews WHERE value_rating IS NOT NULL;
