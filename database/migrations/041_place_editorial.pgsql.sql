-- Structured editorial for a place: the sections a traveler actually wants answered, kept in their
-- own columns instead of mashed into one prose blob.
--
-- Why a separate table rather than columns on `places` or a longer `reviews.body`:
--
--   * `places` rows are created by ORDINARY TRAVELERS the moment they review something. A place is
--     a shared, user-owned entity; this table is the RuinMyTrip team's writing about it. Keeping
--     them apart means an editorial write can never touch a user's row, and a place with no
--     editorial simply has no row here.
--   * `reviews` is untouched on purpose. The editorial review (rating, headline, body) still lives
--     there exactly as before, so every existing rule about averages, editorial exclusion and the
--     traveler/editorial split keeps working with no changes and no re-testing.
--
-- Every column except place_id is nullable. Sections that cannot be verified for a given
-- attraction are left NULL and simply do not render, which is the difference between a useful page
-- and a template with "information not available" printed twelve times.
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
