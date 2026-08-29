-- sqlite mirror of 047_place_attributes.pgsql.sql. See that file for the full rationale.
--
-- Two deliberate differences, both because SQLite cannot express them in ALTER TABLE:
--
--   * the price_level 1..4 range is a CHECK constraint on Postgres and is enforced in PHP
--     (rmt_place_normalize_price_level) on every path that writes it, so local SQLite and
--     production agree on behaviour even though only one of them agrees on the constraint;
--   * ALTER TABLE ... ADD COLUMN IF NOT EXISTS does not exist, which is harmless: the migrator
--     applies every migration exactly once and records it in schema_migrations.
--
-- Booleans are stored 0/1, which is what PDO returns for Postgres booleans too once cast in PHP.

CREATE TABLE IF NOT EXISTS place_categories (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  parent_id INTEGER REFERENCES place_categories(id) ON DELETE SET NULL,
  bucket    TEXT NOT NULL,
  slug      TEXT NOT NULL UNIQUE,
  name      TEXT NOT NULL,
  plural    TEXT NOT NULL,
  sort      INTEGER NOT NULL DEFAULT 0,
  status    TEXT NOT NULL DEFAULT 'active'
);
CREATE INDEX IF NOT EXISTS idx_place_categories_bucket ON place_categories(bucket, sort, name);

ALTER TABLE places ADD COLUMN category_id     INTEGER REFERENCES place_categories(id) ON DELETE SET NULL;
ALTER TABLE places ADD COLUMN street_address  TEXT;
ALTER TABLE places ADD COLUMN neighborhood    TEXT;
ALTER TABLE places ADD COLUMN region          TEXT;
ALTER TABLE places ADD COLUMN postal_code     TEXT;
ALTER TABLE places ADD COLUMN lat             REAL;
ALTER TABLE places ADD COLUMN lng             REAL;
ALTER TABLE places ADD COLUMN phone           TEXT;
ALTER TABLE places ADD COLUMN website_url     TEXT;
ALTER TABLE places ADD COLUMN price_level     INTEGER;
ALTER TABLE places ADD COLUMN timezone        TEXT;
ALTER TABLE places ADD COLUMN data_source     TEXT;
ALTER TABLE places ADD COLUMN data_source_url TEXT;
ALTER TABLE places ADD COLUMN data_checked_at TEXT;

CREATE INDEX IF NOT EXISTS idx_places_latlng     ON places(lat, lng) WHERE lat IS NOT NULL AND lng IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_places_category   ON places(category_id, status);
CREATE INDEX IF NOT EXISTS idx_places_dest_price ON places(destination_id, price_level);

CREATE TABLE IF NOT EXISTS place_slug_history (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  place_id   INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  slug       TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_place_slug_history_place ON place_slug_history(place_id);

CREATE TABLE IF NOT EXISTS place_hours (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  place_id      INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  day_of_week   INTEGER NOT NULL,
  opens         TEXT,
  closes        TEXT,
  closed        INTEGER NOT NULL DEFAULT 0,
  valid_from    TEXT,
  valid_through TEXT,
  sort          INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT place_hours_day_range CHECK (day_of_week >= 0 AND day_of_week <= 6),
  CONSTRAINT place_hours_shape CHECK (
    (closed = 1 AND opens IS NULL AND closes IS NULL) OR
    (closed = 0 AND opens IS NOT NULL AND closes IS NOT NULL)
  )
);
CREATE INDEX IF NOT EXISTS idx_place_hours_place ON place_hours(place_id, day_of_week, sort);

CREATE TABLE IF NOT EXISTS place_photos (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  place_id        INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  review_photo_id INTEGER REFERENCES review_photos(id) ON DELETE CASCADE,
  storage_key     TEXT,
  url             TEXT,
  caption         TEXT,
  alt_text        TEXT,
  credit          TEXT,
  license         TEXT,
  source_url      TEXT,
  uploaded_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
  width           INTEGER,
  height          INTEGER,
  bytes           INTEGER,
  is_cover        INTEGER NOT NULL DEFAULT 0,
  sort            INTEGER NOT NULL DEFAULT 0,
  status          TEXT NOT NULL DEFAULT 'published',
  created_at      TEXT NOT NULL,
  CONSTRAINT place_photos_has_image CHECK (storage_key IS NOT NULL OR url IS NOT NULL)
);
CREATE INDEX IF NOT EXISTS idx_place_photos_place ON place_photos(place_id, status, sort, id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_place_photos_one_cover ON place_photos(place_id) WHERE is_cover = 1;
CREATE UNIQUE INDEX IF NOT EXISTS idx_place_photos_review_photo ON place_photos(review_photo_id) WHERE review_photo_id IS NOT NULL;

INSERT OR IGNORE INTO place_categories (bucket, slug, name, plural, sort) VALUES
  ('hotel','resort','Resort','Resorts',10),
  ('hotel','boutique-hotel','Boutique Hotel','Boutique Hotels',20),
  ('hotel','luxury-hotel','Luxury Hotel','Luxury Hotels',30),
  ('hotel','budget-hotel','Budget Hotel','Budget Hotels',40),
  ('hotel','motel','Motel','Motels',50),
  ('hotel','hostel','Hostel','Hostels',60),
  ('hotel','bed-and-breakfast','Bed and Breakfast','Bed and Breakfasts',70),
  ('hotel','guesthouse','Guesthouse','Guesthouses',80),
  ('hotel','vacation-rental','Vacation Rental','Vacation Rentals',90),
  ('hotel','campground','Campground','Campgrounds',100),
  ('restaurant','fine-dining','Fine Dining','Fine Dining',10),
  ('restaurant','bistro','Bistro','Bistros',20),
  ('restaurant','steakhouse','Steakhouse','Steakhouses',30),
  ('restaurant','seafood','Seafood','Seafood Restaurants',40),
  ('restaurant','pizzeria','Pizzeria','Pizzerias',50),
  ('restaurant','cafe','Cafe','Cafes',60),
  ('restaurant','bakery','Bakery','Bakeries',70),
  ('restaurant','street-food','Street Food','Street Food',80),
  ('restaurant','bar','Bar','Bars',90),
  ('restaurant','pub','Pub','Pubs',100),
  ('restaurant','nightclub','Nightclub','Nightclubs',110),
  ('restaurant','vegetarian','Vegetarian','Vegetarian Restaurants',120),
  ('attraction','museum','Museum','Museums',10),
  ('attraction','art-gallery','Art Gallery','Art Galleries',20),
  ('attraction','landmark','Landmark','Landmarks',30),
  ('attraction','historic-site','Historic Site','Historic Sites',40),
  ('attraction','religious-site','Religious Site','Religious Sites',50),
  ('attraction','park','Park','Parks',60),
  ('attraction','garden','Garden','Gardens',70),
  ('attraction','beach','Beach','Beaches',80),
  ('attraction','viewpoint','Viewpoint','Viewpoints',90),
  ('attraction','theme-park','Theme Park','Theme Parks',100),
  ('attraction','zoo-aquarium','Zoo or Aquarium','Zoos and Aquariums',110),
  ('attraction','market','Market','Markets',120),
  ('attraction','shopping','Shopping','Shopping',130),
  ('attraction','casino','Casino','Casinos',140),
  ('attraction','theater','Theater','Theaters',150),
  ('attraction','natural-wonder','Natural Wonder','Natural Wonders',160),
  ('experience','walking-tour','Walking Tour','Walking Tours',10),
  ('experience','day-trip','Day Trip','Day Trips',20),
  ('experience','boat-tour','Boat Tour','Boat Tours',30),
  ('experience','food-tour','Food Tour','Food Tours',40),
  ('experience','outdoor-activity','Outdoor Activity','Outdoor Activities',50),
  ('experience','class-workshop','Class or Workshop','Classes and Workshops',60),
  ('experience','transport','Transport','Transport',70),
  ('experience','spa-wellness','Spa and Wellness','Spas and Wellness',80);
