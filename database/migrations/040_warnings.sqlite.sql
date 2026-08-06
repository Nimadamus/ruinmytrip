-- SQLite mirror of 040_warnings.pgsql.sql (local dev). See that file for the design notes.
-- CHECK constraints are inlined here because SQLite cannot ADD CONSTRAINT after the fact.

CREATE TABLE IF NOT EXISTS warnings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  slug TEXT NOT NULL,
  category TEXT NOT NULL,
  body TEXT NOT NULL,
  advice TEXT,
  severity INTEGER NOT NULL DEFAULT 2 CHECK (severity BETWEEN 1 AND 4),
  date_experienced TEXT,
  season_month INTEGER CHECK (season_month IS NULL OR season_month BETWEEN 1 AND 12),
  location_detail TEXT,
  cost_impact_usd INTEGER,
  provider_type TEXT,
  provider_name TEXT,
  traveler_type TEXT,
  attested INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'pending'
    CHECK (status IN ('draft','pending','approved','rejected','needs_revision')),
  verification TEXT NOT NULL DEFAULT 'unverified'
    CHECK (verification IN ('unverified','verified','disputed')),
  moderation_note TEXT,
  moderated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  moderated_at TEXT,
  helpful_count INTEGER NOT NULL DEFAULT 0,
  not_helpful_count INTEGER NOT NULL DEFAULT 0,
  view_count INTEGER NOT NULL DEFAULT 0,
  featured INTEGER NOT NULL DEFAULT 0,
  source_url TEXT,
  dedupe_hash TEXT,
  last_reviewed_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_warnings_dest ON warnings(destination_id, status);
CREATE INDEX IF NOT EXISTS idx_warnings_cat ON warnings(category, status);
CREATE INDEX IF NOT EXISTS idx_warnings_user ON warnings(user_id, status);
CREATE INDEX IF NOT EXISTS idx_warnings_recent ON warnings(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_warnings_dedupe ON warnings(dedupe_hash);

CREATE TABLE IF NOT EXISTS warning_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  warning_id INTEGER NOT NULL REFERENCES warnings(id) ON DELETE CASCADE,
  url TEXT NOT NULL,
  storage_key TEXT,
  caption TEXT,
  width INTEGER,
  height INTEGER,
  bytes INTEGER,
  sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_warning_photos ON warning_photos(warning_id, sort);

CREATE TABLE IF NOT EXISTS warning_votes (
  warning_id INTEGER NOT NULL REFERENCES warnings(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  vote TEXT NOT NULL DEFAULT 'helpful',
  created_at TEXT NOT NULL,
  PRIMARY KEY (warning_id, user_id)
);

CREATE TABLE IF NOT EXISTS warning_moderation_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  warning_id INTEGER NOT NULL REFERENCES warnings(id) ON DELETE CASCADE,
  actor_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  field TEXT NOT NULL,
  from_value TEXT,
  to_value TEXT,
  note TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_warning_modlog ON warning_moderation_log(warning_id, id);

CREATE TABLE IF NOT EXISTS warning_responses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  warning_id INTEGER NOT NULL REFERENCES warnings(id) ON DELETE CASCADE,
  responder_name TEXT NOT NULL,
  responder_role TEXT,
  contact_email TEXT,
  body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL,
  approved_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_warning_responses ON warning_responses(warning_id, status);

CREATE TABLE IF NOT EXISTS staleness_reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  reporter_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  target_type TEXT NOT NULL,
  target_id INTEGER NOT NULL,
  note TEXT,
  status TEXT NOT NULL DEFAULT 'open',
  resolved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  resolved_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_staleness_open ON staleness_reports(status, id);
