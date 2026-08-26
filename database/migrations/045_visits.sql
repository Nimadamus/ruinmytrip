CREATE TABLE IF NOT EXISTS visits (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  PRIMARY KEY (user_id, destination_id)
);
CREATE INDEX IF NOT EXISTS idx_visits_dest ON visits (destination_id, created_at);
