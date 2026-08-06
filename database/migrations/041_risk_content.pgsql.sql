-- Destination risk reports + the editorial layer that makes them trustworthy.
--
-- A destination page is now a RISK REPORT, not a brochure. The structured pieces live in their
-- own tables rather than one blob of HTML so that:
--   * each section can carry its own sources and its own "last reviewed" date — travel facts
--     rot at wildly different rates (a visa rule vs. a metro line closure)
--   * the owner can edit one section in the admin without touching the rest
--   * search, SEO landing pages and the homepage can query a single section type across
--     every destination
--
-- content_type is the trust taxonomy, stored per section and rendered as a visible label:
--   fact      — a checkable, sourced statement (a price, a rule, a date)
--   editorial — RuinMyTrip's own guidance, clearly ours
--   alert     — time-sensitive; renders with a prominent date and expires from "trending"
-- Traveler opinion and unverified reports never live here; they are `warnings` and `reviews`.

ALTER TABLE destinations ADD COLUMN IF NOT EXISTS risk_level INTEGER;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS risk_summary TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS worth_visiting TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS best_months TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS worst_months TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS last_reviewed_at TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS featured INTEGER NOT NULL DEFAULT 0;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS airport_codes TEXT;

ALTER TABLE destinations DROP CONSTRAINT IF EXISTS destinations_risk_level_ck;
ALTER TABLE destinations ADD CONSTRAINT destinations_risk_level_ck
  CHECK (risk_level IS NULL OR risk_level BETWEEN 1 AND 4);

CREATE TABLE IF NOT EXISTS destination_risk_sections (
  id SERIAL PRIMARY KEY,
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
  id SERIAL PRIMARY KEY,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  sort INTEGER NOT NULL DEFAULT 0,
  last_reviewed_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_dest_faqs ON destination_faqs(destination_id, sort);

-- Editorially written SEO landing pages ("What can ruin a trip to Paris?", "Paris tourist
-- scams to avoid"). Deliberately a table of REAL, reviewed pages rather than a URL pattern
-- that renders something for any slug: a page exists only when someone wrote and reviewed it,
-- so the site can never emit a thin auto-generated page for a destination it knows nothing
-- about. `status` gates publication; unpublished slugs 404 rather than rendering a stub.
CREATE TABLE IF NOT EXISTS seo_landing_pages (
  id SERIAL PRIMARY KEY,
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

-- Owner-editable site configuration, so routine content decisions (which destinations are
-- featured on the homepage, how many trending warnings to show) never require a code change.
CREATE TABLE IF NOT EXISTS site_settings (
  key TEXT PRIMARY KEY,
  value TEXT,
  updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  updated_at TEXT
);
