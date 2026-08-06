-- SQLite mirror of 041_risk_content.pgsql.sql. See that file for design notes.
-- SQLite has no ADD COLUMN IF NOT EXISTS; these columns are new in this migration and the
-- migrator applies each version exactly once, so a plain ADD COLUMN is safe and correct.

ALTER TABLE destinations ADD COLUMN risk_level INTEGER;
ALTER TABLE destinations ADD COLUMN risk_summary TEXT;
ALTER TABLE destinations ADD COLUMN worth_visiting TEXT;
ALTER TABLE destinations ADD COLUMN best_months TEXT;
ALTER TABLE destinations ADD COLUMN worst_months TEXT;
ALTER TABLE destinations ADD COLUMN last_reviewed_at TEXT;
ALTER TABLE destinations ADD COLUMN featured INTEGER NOT NULL DEFAULT 0;
ALTER TABLE destinations ADD COLUMN airport_codes TEXT;

CREATE TABLE IF NOT EXISTS destination_risk_sections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  section_key TEXT NOT NULL,
  heading TEXT,
  body TEXT NOT NULL,
  content_type TEXT NOT NULL DEFAULT 'editorial',
  severity INTEGER,
  sources_json TEXT,
  sort INTEGER NOT NULL DEFAULT 0,
  last_reviewed_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_risk_sections_uniq
  ON destination_risk_sections(destination_id, section_key);

CREATE TABLE IF NOT EXISTS destination_faqs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  sort INTEGER NOT NULL DEFAULT 0,
  last_reviewed_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_dest_faqs ON destination_faqs(destination_id, sort);

CREATE TABLE IF NOT EXISTS seo_landing_pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT UNIQUE NOT NULL,
  template TEXT NOT NULL,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE CASCADE,
  category TEXT,
  h1 TEXT NOT NULL,
  title_tag TEXT NOT NULL,
  meta_description TEXT NOT NULL,
  intro TEXT,
  body TEXT,
  sources_json TEXT,
  status TEXT NOT NULL DEFAULT 'draft',
  author_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  last_reviewed_at TEXT,
  view_count INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_seo_pages_dest ON seo_landing_pages(destination_id, status);
CREATE INDEX IF NOT EXISTS idx_seo_pages_tpl ON seo_landing_pages(template, status);

CREATE TABLE IF NOT EXISTS site_settings (
  key TEXT PRIMARY KEY,
  value TEXT,
  updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  updated_at TEXT
);
