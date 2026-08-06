-- First-party analytics + the affiliate foundation.
--
-- ANALYTICS: a small, first-party event table. No third-party script, no cookie beyond the
-- existing session, and `visitor_key` is a rotating salted hash rather than an identifier that
-- follows anyone around — enough to reconstruct the funnel
--   visitor -> destination page -> warning engagement -> signup -> saved trip / follow
-- and nothing more. Events are written server-side wherever the action happens server-side, so
-- the funnel does not silently lose every visitor with an ad blocker.
--
-- AFFILIATE: links are DATA, not markup. Every outbound partner link is a row here and is
-- rendered through one component, which means the disclosure, the rel attributes and the click
-- accounting can never be forgotten on one page. `active` defaults to 0: nothing is live until
-- a real partner account exists, so the site cannot ship placeholder monetization.

CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGSERIAL PRIMARY KEY,
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
  id SERIAL PRIMARY KEY,
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
