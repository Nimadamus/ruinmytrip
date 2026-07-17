-- Review milestone fields. All nullable so existing rows stay valid.
ALTER TABLE reviews ADD COLUMN slug TEXT;
ALTER TABLE reviews ADD COLUMN what_great TEXT;
ALTER TABLE reviews ADD COLUMN what_ruined TEXT;
ALTER TABLE reviews ADD COLUMN safety_rating INTEGER;
ALTER TABLE reviews ADD COLUMN value_rating INTEGER;
ALTER TABLE reviews ADD COLUMN updated_at TEXT;

-- Server-side validation exists in PHP; these are the DB-level backstop. RLS can't help here
-- (single owner-role connection), so CHECK constraints are the real last line of defence.
ALTER TABLE reviews ADD CONSTRAINT reviews_rating_ck
  CHECK (rating BETWEEN 1 AND 5);
ALTER TABLE reviews ADD CONSTRAINT reviews_safety_ck
  CHECK (safety_rating IS NULL OR safety_rating BETWEEN 1 AND 5);
ALTER TABLE reviews ADD CONSTRAINT reviews_value_ck
  CHECK (value_rating IS NULL OR value_rating BETWEEN 1 AND 5);
ALTER TABLE reviews ADD CONSTRAINT reviews_status_ck
  CHECK (status IN ('draft','published','hidden','removed'));

CREATE INDEX IF NOT EXISTS idx_reviews_user_status ON reviews(user_id, status);
CREATE INDEX IF NOT EXISTS idx_reviews_dest_status ON reviews(destination_id, status);
