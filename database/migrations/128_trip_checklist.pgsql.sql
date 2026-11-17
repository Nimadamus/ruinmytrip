-- Migration 128 - "Before you go" checklist on a trip (app/trip_checklist.php).
-- One row per item ticked off on a trip. The items themselves come from the sourced data files,
-- keyed by a stable hash of their title, so nothing about the content lives here.
CREATE TABLE IF NOT EXISTS trip_checklist (
  trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  item_key TEXT NOT NULL,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  done_at TEXT NOT NULL,
  PRIMARY KEY (trip_id, item_key)
);
