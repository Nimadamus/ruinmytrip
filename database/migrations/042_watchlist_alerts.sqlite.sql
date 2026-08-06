-- SQLite mirror of 042_watchlist_alerts.pgsql.sql. See that file for design notes.

CREATE TABLE IF NOT EXISTS trip_watchlist (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  label TEXT,
  date_from TEXT,
  date_to TEXT,
  note TEXT,
  categories_json TEXT,
  min_severity INTEGER NOT NULL DEFAULT 1 CHECK (min_severity BETWEEN 1 AND 4),
  alert_frequency TEXT NOT NULL DEFAULT 'weekly'
    CHECK (alert_frequency IN ('immediate','daily','weekly','none')),
  last_alerted_at TEXT,
  last_seen_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_watchlist_user ON trip_watchlist(user_id, date_from);
CREATE INDEX IF NOT EXISTS idx_watchlist_dest ON trip_watchlist(destination_id);

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

CREATE TABLE IF NOT EXISTS alert_subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
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

CREATE TABLE IF NOT EXISTS alert_deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
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
