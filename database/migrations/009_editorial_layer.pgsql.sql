-- Editorial layer.
--
-- RuinMyTrip launches with no community reviews. Rather than fake traveler accounts, the site
-- publishes clearly labelled EDITORIAL content written by the RuinMyTrip team from published
-- research. Two rules make that honest rather than decorative:
--
--   1. Authorship is the single source of truth. An editorial item is one whose author has
--      users.role = 'editorial'. There is no per-row "editorial" flag that could drift out of
--      sync with who actually wrote it, and no way to publish editorial content without it
--      rendering with an "Official Review" label.
--   2. Editorial ratings NEVER enter the community average. Destination pages compute the
--      community score from non-editorial authors only, so the site can never quote its own
--      opinion back as if travelers had said it.
--
-- Photo provenance is stored alongside the image because every editorial photo is a real,
-- freely licensed photograph and the licence requires attribution.

ALTER TABLE destinations ADD COLUMN IF NOT EXISTS hero_credit TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS hero_license TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS hero_source_url TEXT;

ALTER TABLE guides ADD COLUMN IF NOT EXISTS updated_at TEXT;

-- Practical, checkable tips shown on a destination page. Editorial-owned; a tip is a fact
-- (a price, a time, a line name, a rule), never an opinion.
CREATE TABLE IF NOT EXISTS destination_tips (
  id SERIAL PRIMARY KEY,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_dest_tips ON destination_tips(destination_id, sort);

-- Who invited this account, by username. Set only from a real /invite link click at
-- registration time. Nullable forever; nothing is gated on it.
ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by TEXT;
