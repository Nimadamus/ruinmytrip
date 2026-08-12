-- Structured editorial for a place. See 041_place_editorial.pgsql.sql for the full rationale:
-- `places` is user-owned, `reviews` is deliberately untouched, and every section column is
-- nullable so an unverifiable section renders as nothing rather than as boilerplate.
CREATE TABLE IF NOT EXISTS place_editorial (
  place_id INTEGER PRIMARY KEY REFERENCES places(id) ON DELETE CASCADE,
  meta_description TEXT,
  what_it_is       TEXT,
  why_go           TEXT,
  the_good         TEXT,
  the_downsides    TEXT,
  best_for         TEXT,
  skip_if          TEXT,
  practical        TEXT,
  location_context TEXT,
  getting_there    TEXT,
  time_needed      TEXT,
  accessibility    TEXT,
  tickets          TEXT,
  verdict          TEXT,
  sources          TEXT,
  created_at       TEXT NOT NULL,
  updated_at       TEXT
);
