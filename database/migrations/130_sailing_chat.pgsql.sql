-- Migration 130 - sailing chat (app/sailing_chat.php).
-- One room per ship and departure day, opened once two members are on the sailing. Only the
-- members on it can read or write; nobody else, signed in or not, ever sees a line.
CREATE TABLE IF NOT EXISTS sailing_rooms (
  id SERIAL PRIMARY KEY,
  ship_key TEXT NOT NULL,
  sail_date TEXT NOT NULL,
  ship TEXT NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (ship_key, sail_date)
);
CREATE TABLE IF NOT EXISTS sailing_messages (
  id SERIAL PRIMARY KEY,
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
