-- Web push (see app/push.php).
--
-- One row per device that asked to be told. The endpoint is the device's address at the browser
-- vendor's push service and is unique across members; re-registering the same endpoint replaces
-- the row. p256dh + auth are the subscription's public key and secret, base64url, used to
-- encrypt every payload for that device alone.
--
-- notifications.pushed_at is the claim: NULL means "not yet pushed", and the sender sets it with
-- a guarded UPDATE before sending so two concurrent requests never both deliver the same row.
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
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
ALTER TABLE notifications ADD COLUMN pushed_at TEXT;
CREATE INDEX IF NOT EXISTS idx_notifications_unpushed ON notifications (pushed_at, created_at);
