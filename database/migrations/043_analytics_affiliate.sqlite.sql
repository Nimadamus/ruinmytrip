-- SQLite mirror of 043_analytics_affiliate.pgsql.sql. See that file for design notes.

CREATE TABLE IF NOT EXISTS analytics_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  visitor_key TEXT,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  target_type TEXT,
  target_id INTEGER,
  path TEXT,
  referrer TEXT,
  meta_json TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_events_name_time ON analytics_events(name, created_at);
CREATE INDEX IF NOT EXISTS idx_events_visitor ON analytics_events(visitor_key, created_at);
CREATE INDEX IF NOT EXISTS idx_events_dest ON analytics_events(destination_id, name);

CREATE TABLE IF NOT EXISTS affiliate_links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT UNIQUE NOT NULL,
  label TEXT NOT NULL,
  provider TEXT NOT NULL,
  kind TEXT NOT NULL,
  target_url TEXT NOT NULL,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE CASCADE,
  category TEXT,
  blurb TEXT,
  active INTEGER NOT NULL DEFAULT 0,
  sort INTEGER NOT NULL DEFAULT 0,
  click_count INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_affiliate_dest ON affiliate_links(destination_id, active, sort);
CREATE INDEX IF NOT EXISTS idx_affiliate_kind ON affiliate_links(kind, active, sort);
