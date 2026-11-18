-- Migration 129 - "Tell me when someone joins this sailing" (app/sailings.php).
-- An email, a ship and a departure day from somebody with no account (or a member who wants the
-- email). Double opt in, emailed only when a real member posts the same ship and day.
CREATE TABLE IF NOT EXISTS sailing_alerts (
  id SERIAL PRIMARY KEY,
  email TEXT NOT NULL,
  ship_key TEXT NOT NULL,
  ship_name TEXT NOT NULL,
  sail_date TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  token_hash TEXT NOT NULL UNIQUE,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  visitor TEXT,
  created_at TEXT NOT NULL,
  confirmed_at TEXT,
  notified_at TEXT,
  notify_count INTEGER NOT NULL DEFAULT 0,
  acq_source TEXT,
  acq_medium TEXT,
  acq_campaign TEXT,
  acq_content TEXT
);
CREATE INDEX IF NOT EXISTS idx_sailing_alerts_sailing ON sailing_alerts (ship_key, sail_date, status);
CREATE INDEX IF NOT EXISTS idx_sailing_alerts_email ON sailing_alerts (email);
