-- sqlite mirror of 003. NOTE: sqlite cannot ALTER TABLE ADD CONSTRAINT, so the CHECK
-- constraints in the pgsql version have no equivalent here. Local dev therefore enforces
-- rating/status validity in PHP only. Production (pgsql) has both layers.
ALTER TABLE reviews ADD COLUMN slug TEXT;
ALTER TABLE reviews ADD COLUMN what_great TEXT;
ALTER TABLE reviews ADD COLUMN what_ruined TEXT;
ALTER TABLE reviews ADD COLUMN safety_rating INTEGER;
ALTER TABLE reviews ADD COLUMN value_rating INTEGER;
ALTER TABLE reviews ADD COLUMN updated_at TEXT;

CREATE INDEX IF NOT EXISTS idx_reviews_user_status ON reviews(user_id, status);
CREATE INDEX IF NOT EXISTS idx_reviews_dest_status ON reviews(destination_id, status);
