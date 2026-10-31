-- Trip chat.
--
-- A trip several people are planning had a page they could all edit and no place to talk, so the
-- talking happened in pairs of private messages, and the third person on the trip was never in the
-- conversation about it. This is one shared thread per trip, readable and writable only by the
-- people on it (the owner and every active member), and never shown to anybody else.
--
-- trip_chat_reads holds the last line each member has seen, which is all an unread count needs.
CREATE TABLE IF NOT EXISTS trip_messages (
  id SERIAL PRIMARY KEY,
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
