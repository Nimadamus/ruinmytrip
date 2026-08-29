-- Migration 052 - the contribution funnel.
--
-- We are about to spend real effort trying to get the first traveler reviews, and without this we
-- would be guessing where people give up. Every change to a CTA, a form or a signup step would be
-- an opinion rather than a measurement.
--
-- What it deliberately does NOT hold:
--
--   * no user id. "Did a signed-in person finish" is a funnel question and needs a flag, not an
--     identity. A table that cannot name anybody cannot leak anybody, and the questions we
--     actually have -- where do people drop out, which CTA produces reviews -- are all answerable
--     without knowing who.
--   * no IP address, no user agent, no referrer.
--   * no review text. The review belongs in the review system; an analytics row that carried a
--     copy would be a second place to leak it from and a second thing to keep in step.
--
-- `journey` is a random token minted per session and rotated after a publish. It links the steps
-- of one attempt together so a funnel is a funnel rather than a pile of unrelated counters. It
-- identifies an attempt, not a person, and it is not stored anywhere alongside anything that
-- could name one.
--
-- `source` records where the attempt started (a place page, a destination prompt, /contribute) so
-- we can tell which surfaces actually produce reviews rather than which ones feel busiest.

CREATE TABLE IF NOT EXISTS contribution_events (
  id             SERIAL PRIMARY KEY,
  event          TEXT NOT NULL,
  source         TEXT,
  journey        TEXT,
  place_id       INTEGER,
  destination_id INTEGER,
  is_authed      SMALLINT NOT NULL DEFAULT 0,
  reason         TEXT,
  created_at     TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_contribution_events_event ON contribution_events(event, created_at);
CREATE INDEX IF NOT EXISTS idx_contribution_events_journey ON contribution_events(journey);
CREATE INDEX IF NOT EXISTS idx_contribution_events_source ON contribution_events(source, event);
