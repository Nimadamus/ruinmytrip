-- Yelp-style "was this review useful/funny/cool" votes. A separate table (not the generic
-- `likes` table) because a single user can cast up to three independent votes on the same
-- review -- one per vote_type -- which a (user_id,target_type,target_id) primary key can't hold.
CREATE TABLE IF NOT EXISTS review_votes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  review_id INTEGER NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  vote_type TEXT NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (review_id, user_id, vote_type)
);
CREATE INDEX IF NOT EXISTS idx_review_votes_review ON review_votes(review_id, vote_type);
CREATE INDEX IF NOT EXISTS idx_review_votes_user ON review_votes(user_id);
