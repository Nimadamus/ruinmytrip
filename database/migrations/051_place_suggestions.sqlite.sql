-- sqlite mirror of 051_place_suggestions.pgsql.sql. See that file for the rationale: a suggestion
-- is a row in a queue, never a place, and a human decides.

CREATE TABLE IF NOT EXISTS place_suggestions (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  city          TEXT NOT NULL,
  type          TEXT NOT NULL,
  website_url   TEXT,
  note          TEXT,
  suggested_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  status        TEXT NOT NULL DEFAULT 'pending',
  resolved_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  resolved_at   TEXT,
  place_id      INTEGER REFERENCES places(id) ON DELETE SET NULL,
  created_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_place_suggestions_status ON place_suggestions(status, created_at);
CREATE INDEX IF NOT EXISTS idx_place_suggestions_user ON place_suggestions(suggested_by, created_at);
