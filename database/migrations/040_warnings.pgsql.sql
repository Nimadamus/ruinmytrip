-- Travel warnings: the core entity of RuinMyTrip.
--
-- A warning is one specific, dated, categorised problem a traveler hit at a destination —
-- a scam, a fee, a closure, a transport mistake. It is deliberately NOT a review:
--   * a review rates a place 1-5; a warning describes one avoidable problem
--   * a review is published immediately; a warning goes through moderation before it is public
--   * a warning carries a severity, a date experienced, and advice for avoiding it
--
-- Two separate state fields, on purpose:
--   status       — MODERATION. Has a human let this be published at all?
--   verification — EVIDENCE. Has anyone corroborated the claim? Defaults to 'unverified' and
--                  stays there forever unless a moderator acts. A warning is an allegation
--                  until then, and every render path labels it that way.
--
-- Defamation safeguard: naming a business (provider_name) is allowed, but such a warning is
-- an unverified first-person account until a moderator marks it otherwise, and the business
-- can file a response (warning_responses).

CREATE TABLE IF NOT EXISTS warnings (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  slug TEXT NOT NULL,
  category TEXT NOT NULL,
  body TEXT NOT NULL,
  advice TEXT,
  severity INTEGER NOT NULL DEFAULT 2,
  date_experienced TEXT,
  season_month INTEGER,
  location_detail TEXT,
  cost_impact_usd INTEGER,
  provider_type TEXT,
  provider_name TEXT,
  traveler_type TEXT,
  attested INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'pending',
  verification TEXT NOT NULL DEFAULT 'unverified',
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

-- Second, independent backstop behind app/warnings.php validation.
ALTER TABLE warnings DROP CONSTRAINT IF EXISTS warnings_severity_ck;
ALTER TABLE warnings ADD CONSTRAINT warnings_severity_ck CHECK (severity BETWEEN 1 AND 4);
ALTER TABLE warnings DROP CONSTRAINT IF EXISTS warnings_status_ck;
ALTER TABLE warnings ADD CONSTRAINT warnings_status_ck
  CHECK (status IN ('draft','pending','approved','rejected','needs_revision'));
ALTER TABLE warnings DROP CONSTRAINT IF EXISTS warnings_verification_ck;
ALTER TABLE warnings ADD CONSTRAINT warnings_verification_ck
  CHECK (verification IN ('unverified','verified','disputed'));
ALTER TABLE warnings DROP CONSTRAINT IF EXISTS warnings_month_ck;
ALTER TABLE warnings ADD CONSTRAINT warnings_month_ck
  CHECK (season_month IS NULL OR season_month BETWEEN 1 AND 12);

CREATE INDEX IF NOT EXISTS idx_warnings_dest ON warnings(destination_id, status);
CREATE INDEX IF NOT EXISTS idx_warnings_cat ON warnings(category, status);
CREATE INDEX IF NOT EXISTS idx_warnings_user ON warnings(user_id, status);
CREATE INDEX IF NOT EXISTS idx_warnings_recent ON warnings(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_warnings_dedupe ON warnings(dedupe_hash);

CREATE TABLE IF NOT EXISTS warning_photos (
  id SERIAL PRIMARY KEY,
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

-- One vote per person per warning. helpful_count on warnings is a denormalised cache of this
-- table, rebuilt on every write, so a sort by helpfulness never needs a join.
CREATE TABLE IF NOT EXISTS warning_votes (
  warning_id INTEGER NOT NULL REFERENCES warnings(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  vote TEXT NOT NULL DEFAULT 'helpful',
  created_at TEXT NOT NULL,
  PRIMARY KEY (warning_id, user_id)
);

-- Append-only audit of every moderation decision. Who changed what, when, and why — so a
-- rejection or a verification badge can always be traced back to a person.
CREATE TABLE IF NOT EXISTS warning_moderation_log (
  id SERIAL PRIMARY KEY,
  warning_id INTEGER NOT NULL REFERENCES warnings(id) ON DELETE CASCADE,
  actor_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  field TEXT NOT NULL,
  from_value TEXT,
  to_value TEXT,
  note TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_warning_modlog ON warning_moderation_log(warning_id, id);

-- Right of reply. A named business can answer a warning about it; the response renders inline,
-- clearly labelled, and never edits or hides the original.
CREATE TABLE IF NOT EXISTS warning_responses (
  id SERIAL PRIMARY KEY,
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

-- "Report outdated information" — distinct from abuse reports, which stay in `reports`.
CREATE TABLE IF NOT EXISTS staleness_reports (
  id SERIAL PRIMARY KEY,
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
