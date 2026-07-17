CREATE TABLE IF NOT EXISTS badges (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT UNIQUE NOT NULL,
  name TEXT NOT NULL,
  description TEXT,
  icon TEXT
);
CREATE TABLE IF NOT EXISTS user_badges (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  badge_id INTEGER NOT NULL REFERENCES badges(id) ON DELETE CASCADE,
  awarded_at TEXT NOT NULL,
  PRIMARY KEY (user_id, badge_id)
);
INSERT OR IGNORE INTO badges (slug, name, description, icon) VALUES
  ('founding-traveler','Founding Traveler','One of the first travelers to join RuinMyTrip and contribute a verified-email account.','◈');
