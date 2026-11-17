-- Migration 128 - trip checklist. See the pgsql file.
CREATE TABLE IF NOT EXISTS trip_checklist (
  trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  item_key TEXT NOT NULL,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  done_at TEXT NOT NULL,
  PRIMARY KEY (trip_id, item_key)
);
