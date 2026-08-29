-- Migration 049 - category-specific review subratings and traveler type.
--
-- A review has always carried one overall rating plus two fixed extras, safety_rating and
-- value_rating, as columns. That shape does not extend: a hotel wants rooms, cleanliness, service
-- and location; a restaurant wants food, atmosphere; an attraction wants crowds and accessibility.
-- Adding a column per dimension means a wide table of mostly-NULLs, a migration for every new
-- aspect, and a schema that encodes today's vocabulary as though it were permanent.
--
-- So aspects become rows.
--
--   * reviews.rating is UNCHANGED and remains the single overall score. It is read on the place
--     page, the destination page, the profile, the leaderboard and every aggregate on the site,
--     and it stays one indexed integer column on the row so none of that gets slower.
--   * review_ratings holds everything else, one row per (review, aspect). The unique index is the
--     rule, not a convention: a review cannot hold two opinions of the same aspect.
--   * safety_rating and value_rating are backfilled into review_ratings so there is one place to
--     read an aspect from. The columns are kept and are maintained by the same write path as a
--     denormalised mirror, because rmt_place_stats() and the place page already read them; a
--     column derived from the aspects on every write cannot drift from them.
--
-- traveler_type is a nullable column rather than a row because it is one value per review, not a
-- list, and it is asked once. The allowed set is constrained here and in PHP.
--
-- Additive: no column dropped, retyped or rewritten; no existing review changes meaning. Flags and
-- scores are integers on both drivers (see 048 for why that matters).

CREATE TABLE IF NOT EXISTS review_ratings (
  id        SERIAL PRIMARY KEY,
  review_id INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
  aspect    TEXT NOT NULL,
  value     SMALLINT NOT NULL,
  CONSTRAINT review_ratings_value_range CHECK (value >= 1 AND value <= 5)
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_review_ratings_unique ON review_ratings(review_id, aspect);
-- Aggregation reads every rating for a set of reviews and groups by aspect. The unique index above
-- already serves the review_id lookup; this one serves "how does this aspect score generally",
-- which is what a future best-of query filters on.
CREATE INDEX IF NOT EXISTS idx_review_ratings_aspect ON review_ratings(aspect, value);

ALTER TABLE reviews ADD COLUMN IF NOT EXISTS traveler_type TEXT;
DO $do$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'reviews_traveler_type_allowed') THEN
    ALTER TABLE reviews ADD CONSTRAINT reviews_traveler_type_allowed
      CHECK (traveler_type IS NULL OR traveler_type IN ('solo','couple','family','friends','business','other'));
  END IF;
END $do$;
CREATE INDEX IF NOT EXISTS idx_reviews_traveler_type ON reviews(traveler_type) WHERE traveler_type IS NOT NULL;

-- Backfill: the two existing aspect columns become ordinary aspect rows. Only non-null values move,
-- so a review that never answered stays unanswered rather than acquiring a made-up score.
INSERT INTO review_ratings (review_id, aspect, value)
SELECT id, 'safety', safety_rating FROM reviews WHERE safety_rating IS NOT NULL
ON CONFLICT (review_id, aspect) DO NOTHING;

INSERT INTO review_ratings (review_id, aspect, value)
SELECT id, 'value', value_rating FROM reviews WHERE value_rating IS NOT NULL
ON CONFLICT (review_id, aspect) DO NOTHING;
