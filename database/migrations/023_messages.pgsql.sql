-- Direct messages between two users, plus the blocks-table enforcement it depends on. The
-- `blocks` table has existed since the base schema (safety.php and the meetup page both already
-- promise a "block" affordance) but nothing ever read or wrote it -- this migration doesn't touch
-- that table's shape, only adds what messaging needs on top of it.
--
-- One conversation row per unordered pair of users (user_lo_id < user_hi_id keeps the pair
-- canonical so a lookup never has to check both orderings or risk a duplicate row for the same
-- two people).
CREATE TABLE IF NOT EXISTS conversations (
  id SERIAL PRIMARY KEY,
  user_lo_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user_hi_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  last_message_at TEXT,
  created_at TEXT NOT NULL,
  CHECK (user_lo_id < user_hi_id),
  UNIQUE (user_lo_id, user_hi_id)
);
CREATE TABLE IF NOT EXISTS messages (
  id SERIAL PRIMARY KEY,
  conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
  sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  read_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_messages_conv ON messages(conversation_id, id);
CREATE INDEX IF NOT EXISTS idx_conversations_lo ON conversations(user_lo_id, last_message_at);
CREATE INDEX IF NOT EXISTS idx_conversations_hi ON conversations(user_hi_id, last_message_at);
