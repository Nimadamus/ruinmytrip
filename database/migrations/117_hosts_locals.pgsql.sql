-- Hosts & Locals.
--
-- One account, three roles. Traveler is every member. Local is the existing profiles.open_to_meeting
-- with a home city (app/buddies.php), and this adds what a local offers and when. Host is a
-- host_profiles row: an application that a moderator reviews before anybody sees it, and that the
-- host can pause. Overnight stay requests are switched off in code (app/hosts_locals.php) until
-- identity verification exists, so nothing here can book a bed.
--
-- member_references are what members say about each other after a real, accepted connection.
-- They are kept apart from place and city reviews on purpose: a person is not a restaurant.
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS local_offers TEXT;
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS local_availability TEXT;

CREATE TABLE IF NOT EXISTS host_profiles (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'pending',
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  neighborhood TEXT,
  space_type TEXT NOT NULL,
  max_guests INTEGER NOT NULL DEFAULT 1,
  min_nights INTEGER NOT NULL DEFAULT 1,
  max_nights INTEGER NOT NULL DEFAULT 3,
  available_note TEXT,
  about_space TEXT,
  house_rules TEXT,
  accessibility TEXT,
  pets TEXT,
  smoking TEXT,
  id_verified_at TEXT,
  review_note TEXT,
  reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_host_profiles_dest ON host_profiles (destination_id, status);

CREATE TABLE IF NOT EXISTS member_references (
  id SERIAL PRIMARY KEY,
  from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  to_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT NOT NULL,
  basis TEXT NOT NULL,
  rating_overall INTEGER NOT NULL,
  rating_communication INTEGER,
  rating_reliability INTEGER,
  rating_accuracy INTEGER,
  body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'published',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE (from_user_id, to_user_id, kind)
);
CREATE INDEX IF NOT EXISTS idx_member_references_to ON member_references (to_user_id, status);
