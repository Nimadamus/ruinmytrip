-- Migration 116 - trip chat. See the pgsql file.
CREATE TABLE IF NOT EXISTS trip_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_trip_messages_trip ON trip_messages (trip_id, id);
CREATE INDEX IF NOT EXISTS idx_trip_messages_user ON trip_messages (user_id);
CREATE TABLE IF NOT EXISTS trip_chat_reads (
  trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  last_read_id INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (trip_id, user_id)
);
