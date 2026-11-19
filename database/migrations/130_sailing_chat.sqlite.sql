-- Migration 130 - sailing chat. See the pgsql file.
CREATE TABLE IF NOT EXISTS sailing_rooms (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ship_key TEXT NOT NULL,
  sail_date TEXT NOT NULL,
  ship TEXT NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (ship_key, sail_date)
);
CREATE TABLE IF NOT EXISTS sailing_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id INTEGER NOT NULL REFERENCES sailing_rooms(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sailing_messages_room ON sailing_messages (room_id, id);
CREATE INDEX IF NOT EXISTS idx_sailing_messages_user ON sailing_messages (user_id);
CREATE TABLE IF NOT EXISTS sailing_chat_reads (
  room_id INTEGER NOT NULL REFERENCES sailing_rooms(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  last_read_id INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (room_id, user_id)
);
