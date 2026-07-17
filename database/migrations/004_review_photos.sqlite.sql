CREATE TABLE IF NOT EXISTS review_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  review_id INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
  url TEXT NOT NULL,
  storage_key TEXT NOT NULL,
  caption TEXT,
  width INTEGER, height INTEGER, bytes INTEGER,
  sort INTEGER DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_review_photos_review ON review_photos(review_id, sort);
