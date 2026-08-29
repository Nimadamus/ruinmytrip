-- Migration 051 - the queue for places travelers tell us about.
--
-- Sooner or later somebody wants to review a place we do not have, and the two obvious answers are
-- both wrong. Turning them away loses the review, which is the thing this site is short of. Letting
-- them create a live place is how a directory fills with spam, duplicates and businesses that do
-- not exist, and it hands anyone a way to publish an indexable page on our domain.
--
-- So a suggestion is a row in a queue, not a place. It is never public, it creates nothing, and a
-- human decides. Deduplication happens at that point, against the existing name_key rules, because
-- a suggestion for "the Louvre" when we already hold "Louvre Museum" must merge rather than mint.
--
-- The suggester is recorded so we can tell them it landed, and so a stream of junk from one account
-- is visible as one account.

CREATE TABLE IF NOT EXISTS place_suggestions (
  id            SERIAL PRIMARY KEY,
  name          TEXT NOT NULL,
  city          TEXT NOT NULL,
  type          TEXT NOT NULL,
  website_url   TEXT,
  note          TEXT,
  suggested_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  status        TEXT NOT NULL DEFAULT 'pending',   -- pending | added | rejected | duplicate
  resolved_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  resolved_at   TEXT,
  place_id      INTEGER REFERENCES places(id) ON DELETE SET NULL,
  created_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_place_suggestions_status ON place_suggestions(status, created_at);
CREATE INDEX IF NOT EXISTS idx_place_suggestions_user ON place_suggestions(suggested_by, created_at);
