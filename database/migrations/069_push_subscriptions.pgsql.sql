-- See 069_push_subscriptions.sqlite.sql.
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  endpoint TEXT NOT NULL UNIQUE,
  p256dh TEXT NOT NULL,
  auth TEXT NOT NULL,
  user_agent TEXT,
  created_at TEXT NOT NULL,
  last_ok_at TEXT,
  failed_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user ON push_subscriptions (user_id);
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS pushed_at TEXT;
CREATE INDEX IF NOT EXISTS idx_notifications_unpushed ON notifications (pushed_at, created_at);
