-- Adding a city, without half a city ever existing.
--
-- Until now a destination could only arrive through the seed, which means adding one was a code
-- change. That is the real reason Miami has no record: not a policy, an architecture gap.
--
-- The obvious fix is a status column on destinations, and it is the wrong one. A hundred and
-- eighty queries in this application read that table, and a draft row would be visible to every
-- one of them until each was found and filtered, with a leak looking exactly like a normal page.
--
-- So a city being written lives HERE, in a table nothing else reads, and publishing is a single
-- insert into destinations. A city cannot exist half made, no existing query changes, and there is
-- no new way to leak anything.
CREATE TABLE IF NOT EXISTS destination_drafts (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  slug        TEXT NOT NULL,
  name        TEXT NOT NULL,
  country     TEXT NOT NULL DEFAULT '',
  region      TEXT,
  lat         REAL,
  lng         REAL,
  category    TEXT,
  summary     TEXT,
  hero_url    TEXT,
  hero_credit TEXT,
  hero_license TEXT,
  hero_source_url TEXT,
  created_by  INTEGER,
  created_at  TEXT NOT NULL,
  updated_at  TEXT,
  published_destination_id INTEGER
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_destination_drafts_slug ON destination_drafts (slug);
