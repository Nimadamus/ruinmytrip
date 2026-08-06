-- Trip watchlist and warning alerts.
--
-- The watchlist is the reason to hold an account: you tell the site where and when you are
-- going, and it tells you what changed before you leave. Everything here is built around not
-- becoming spam:
--   * alert_frequency is per-trip, not global, and 'weekly' is the default
--   * min_severity lets a user ask only for the problems that would actually change plans
--   * alert_deliveries is an append-only log; the sender checks it so the same warning is
--     never mailed twice for the same trip, and a frequency window is enforced in data rather
--     than in the caller's memory
--   * every subscription carries a stateless unsubscribe token, so one click always works

CREATE TABLE IF NOT EXISTS trip_watchlist (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  label TEXT,
  date_from TEXT,
  date_to TEXT,
  note TEXT,
  categories_json TEXT,
  min_severity INTEGER NOT NULL DEFAULT 1,
  alert_frequency TEXT NOT NULL DEFAULT 'weekly',
  last_alerted_at TEXT,
  last_seen_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
ALTER TABLE trip_watchlist DROP CONSTRAINT IF EXISTS trip_watchlist_freq_ck;
ALTER TABLE trip_watchlist ADD CONSTRAINT trip_watchlist_freq_ck
  CHECK (alert_frequency IN ('immediate','daily','weekly','none'));
ALTER TABLE trip_watchlist DROP CONSTRAINT IF EXISTS trip_watchlist_sev_ck;
ALTER TABLE trip_watchlist ADD CONSTRAINT trip_watchlist_sev_ck
  CHECK (min_severity BETWEEN 1 AND 4);
CREATE INDEX IF NOT EXISTS idx_watchlist_user ON trip_watchlist(user_id, date_from);
CREATE INDEX IF NOT EXISTS idx_watchlist_dest ON trip_watchlist(destination_id);

-- Destination follow, separate from a dated trip: "keep me posted about Lisbon in general".
CREATE TABLE IF NOT EXISTS destination_follows (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  categories_json TEXT,
  min_severity INTEGER NOT NULL DEFAULT 1,
  alert_frequency TEXT NOT NULL DEFAULT 'weekly',
  last_alerted_at TEXT,
  last_seen_at TEXT,
  created_at TEXT NOT NULL,
  PRIMARY KEY (user_id, destination_id)
);
CREATE INDEX IF NOT EXISTS idx_destfollow_dest ON destination_follows(destination_id);

-- Email-only alert subscription for visitors with no account (homepage CTA). Double opt-in:
-- a row is inert until confirmed_at is set by clicking the emailed link.
CREATE TABLE IF NOT EXISTS alert_subscriptions (
  id SERIAL PRIMARY KEY,
  email TEXT NOT NULL,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE CASCADE,
  categories_json TEXT,
  min_severity INTEGER NOT NULL DEFAULT 2,
  frequency TEXT NOT NULL DEFAULT 'weekly',
  token TEXT NOT NULL,
  source TEXT,
  confirmed_at TEXT,
  unsubscribed_at TEXT,
  last_sent_at TEXT,
  created_at TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_alertsub_uniq ON alert_subscriptions(email, destination_id);
CREATE INDEX IF NOT EXISTS idx_alertsub_dest ON alert_subscriptions(destination_id, confirmed_at);

-- Append-only send log. The uniqueness is what makes "do not send the same thing twice" a
-- property of the database rather than a hope about the sender's control flow.
CREATE TABLE IF NOT EXISTS alert_deliveries (
  id SERIAL PRIMARY KEY,
  channel TEXT NOT NULL,
  recipient TEXT NOT NULL,
  warning_id INTEGER REFERENCES warnings(id) ON DELETE CASCADE,
  watchlist_id INTEGER REFERENCES trip_watchlist(id) ON DELETE CASCADE,
  subscription_id INTEGER REFERENCES alert_subscriptions(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_alert_deliv_uniq
  ON alert_deliveries(recipient, warning_id, channel);
CREATE INDEX IF NOT EXISTS idx_alert_deliv_recent ON alert_deliveries(created_at);
