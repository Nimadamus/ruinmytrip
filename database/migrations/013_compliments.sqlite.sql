-- Yelp-style compliments: a small, positive, one-directional signal one traveler can send
-- another's profile. Add-only (no un-compliment) and capped to one of each type per sender/
-- recipient pair so it can't be used to spam a single tone repeatedly.
CREATE TABLE IF NOT EXISTS compliments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  to_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (from_user_id, to_user_id, type)
);
CREATE INDEX IF NOT EXISTS idx_compliments_to ON compliments(to_user_id, type);
