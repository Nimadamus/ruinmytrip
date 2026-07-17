-- Badges are AWARDED BY RULE, never hand-set to flatter a profile. Founding Traveler is
-- seeded here as a definition only; no user holds it until the award rule runs.
CREATE TABLE IF NOT EXISTS badges (
  id SERIAL PRIMARY KEY,
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
INSERT INTO badges (slug, name, description, icon) VALUES
  ('founding-traveler','Founding Traveler','One of the first travelers to join RuinMyTrip and contribute a verified-email account.','◈')
ON CONFLICT (slug) DO NOTHING;
