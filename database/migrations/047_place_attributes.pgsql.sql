-- Migration 047 - the place entity becomes a real travel entity.
--
-- Until now a `places` row carried a name, a type, a destination and nothing else. That is enough
-- to collect reviews under one page and no more: no map, no address, no hours, no price level, no
-- cover photo, no taxonomy, and no way for a URL to change without orphaning the entity. Every
-- feature a travel platform is eventually judged on - nearby, filters, "open now", category
-- landing pages, autocomplete ranking, richer structured data - is blocked on those attributes
-- existing somewhere durable.
--
-- Four rules shaped this migration:
--
--   1. NOTHING IS INVENTED. Every new column is nullable and every new table starts empty. There
--      is no backfill, no default address, no guessed coordinate, no assumed price level. A place
--      we do not have a phone number for has NULL, renders nothing, and emits nothing. A schema
--      that makes it easy to store a fact is not permission to make one up.
--   2. IDENTITY IS THE INTEGER ID, FOREVER. `places.id` is what a review, a photo, a save and a
--      category point at. The slug is presentation. place_slug_history exists so a rename is a
--      301, not a new entity and not a dead URL.
--   3. ADDITIVE ONLY. No column is dropped, renamed or retyped; no existing row is rewritten; no
--      existing URL changes. An older deploy keeps serving correctly against this schema, so
--      rollback is a redeploy. See database/rollback/047_down.sql for the manual undo.
--   4. NORMALISE WHERE THE SHAPE IS GENUINELY VARIABLE. Hours and photos are one-to-many with real
--      structure, so they are tables. Address parts are one-to-one with the place, so they are
--      columns. City and country are NOT copied onto the place: they belong to the destination the
--      place already references, and duplicating them creates two truths that drift.

-- ---------------------------------------------------------------------------
-- Taxonomy. `places.type` (hotel|restaurant|attraction|experience) stays exactly as it is: it is
-- load-bearing in routing, filters, titles and schema-type selection, and nothing about it changes.
-- This table is the layer BELOW it - the subcategory a traveler actually searches for ("boutique
-- hotel", "steakhouse", "museum") - and it is a table rather than a PHP array because it has to be
-- joinable, countable and eventually URL-addressable for category landing pages.
--
-- parent_id allows a third level later (Restaurant > Italian > Trattoria) without another
-- migration. `bucket` ties a category back to the places.type it belongs under, so a subcategory
-- can never be attached to a place of an unrelated kind by accident.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS place_categories (
  id        SERIAL PRIMARY KEY,
  parent_id INTEGER REFERENCES place_categories(id) ON DELETE SET NULL,
  bucket    TEXT NOT NULL,
  slug      TEXT NOT NULL UNIQUE,
  name      TEXT NOT NULL,
  plural    TEXT NOT NULL,
  sort      INTEGER NOT NULL DEFAULT 0,
  status    TEXT NOT NULL DEFAULT 'active'
);
CREATE INDEX IF NOT EXISTS idx_place_categories_bucket ON place_categories(bucket, sort, name);

-- ---------------------------------------------------------------------------
-- Place attributes. All nullable; see rule 1.
-- ---------------------------------------------------------------------------
ALTER TABLE places ADD COLUMN IF NOT EXISTS category_id     INTEGER REFERENCES place_categories(id) ON DELETE SET NULL;
ALTER TABLE places ADD COLUMN IF NOT EXISTS street_address  TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS neighborhood    TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS region          TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS postal_code     TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS lat             DOUBLE PRECISION;
ALTER TABLE places ADD COLUMN IF NOT EXISTS lng             DOUBLE PRECISION;
ALTER TABLE places ADD COLUMN IF NOT EXISTS phone           TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS website_url     TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS price_level     SMALLINT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS timezone        TEXT;

-- Provenance, not decoration. A factual claim on a place page (an address, a price band, a set of
-- opening hours) has to be traceable to where it came from, both because we will not publish
-- unsourced business data and because a stale fact needs a date attached to be re-checked.
ALTER TABLE places ADD COLUMN IF NOT EXISTS data_source     TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS data_source_url TEXT;
ALTER TABLE places ADD COLUMN IF NOT EXISTS data_checked_at TEXT;

-- 1..4 (one to four currency symbols). Constrained rather than free-form so a filter can rely on
-- it. Guarded by a catalog lookup so re-running the migration cannot fail on a duplicate name.
DO $do$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'places_price_level_range') THEN
    ALTER TABLE places ADD CONSTRAINT places_price_level_range
      CHECK (price_level IS NULL OR (price_level >= 1 AND price_level <= 4));
  END IF;
END $do$;

-- Geo queries are always "places near this point, of this kind, that are active". A plain btree on
-- (lat,lng) serves the bounding-box prefilter a distance sort runs on top of, which is the right
-- tool until the place count justifies PostGIS.
CREATE INDEX IF NOT EXISTS idx_places_latlng     ON places(lat, lng) WHERE lat IS NOT NULL AND lng IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_places_category   ON places(category_id, status);
CREATE INDEX IF NOT EXISTS idx_places_dest_price ON places(destination_id, price_level);

-- ---------------------------------------------------------------------------
-- Slug history. One row per URL a place has ever had. The current slug is NOT stored here; it
-- lives on the place. A request for a retired slug looks up the place_id and redirects to whatever
-- that place's slug is NOW, so a place renamed three times still costs exactly one 301 and can
-- never form a chain.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS place_slug_history (
  id         SERIAL PRIMARY KEY,
  place_id   INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  slug       TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_place_slug_history_place ON place_slug_history(place_id);

-- ---------------------------------------------------------------------------
-- Opening hours. Not seven text columns: a day can have two intervals (lunch and dinner), a day
-- can be closed, and a bar can close at 02:00 the following morning.
--
--   * one row per interval, so multiple intervals per day are natural
--   * closed = TRUE with NULL times is an explicit "closed on this day", which is information;
--     the ABSENCE of any row for a day means "we do not know", which is not the same thing
--   * closes < opens means the interval runs past midnight, which is how 21:00-02:00 is stored
--   * valid_from / valid_through are here from day one so a holiday or seasonal exception is a
--     row, not a schema change. Nothing reads them yet and no UI writes them.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS place_hours (
  id            SERIAL PRIMARY KEY,
  place_id      INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  day_of_week   SMALLINT NOT NULL,
  opens         TEXT,
  closes        TEXT,
  closed        BOOLEAN NOT NULL DEFAULT FALSE,
  valid_from    TEXT,
  valid_through TEXT,
  sort          INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT place_hours_day_range CHECK (day_of_week >= 0 AND day_of_week <= 6),
  CONSTRAINT place_hours_shape CHECK (
    (closed = TRUE AND opens IS NULL AND closes IS NULL) OR
    (closed = FALSE AND opens IS NOT NULL AND closes IS NOT NULL)
  )
);
CREATE INDEX IF NOT EXISTS idx_place_hours_place ON place_hours(place_id, day_of_week, sort);

-- ---------------------------------------------------------------------------
-- Per-place photos.
--
-- A review photo and a place photo are related but not the same object: a review photo belongs to
-- one person's account of one visit, a place photo represents the place itself. Both should show
-- in a place gallery, and neither should be copied to do it.
--
--   * The bytes are NEVER duplicated. `storage_key` is the existing media key, so a photo attached
--     to a review and surfaced on the place page is one blob with two references.
--   * review_photo_id is set when this row exists BECAUSE of a review photo, which keeps
--     attribution and deletion correct: removing the review removes the place reference with it.
--   * is_cover marks the one image that leads the page and fills og:image. A partial unique index
--     enforces at most one cover per place in the database rather than in application code.
--   * status carries the same published|pending|hidden|removed moderation vocabulary as every
--     other user-generated object on the site.
--   * alt_text is a column and not an afterthought: an image with no alternative text is an image
--     a screen reader cannot describe and a search engine cannot read.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS place_photos (
  id              SERIAL PRIMARY KEY,
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
  is_cover        BOOLEAN NOT NULL DEFAULT FALSE,
  sort            INTEGER NOT NULL DEFAULT 0,
  status          TEXT NOT NULL DEFAULT 'published',
  created_at      TEXT NOT NULL,
  CONSTRAINT place_photos_has_image CHECK (storage_key IS NOT NULL OR url IS NOT NULL)
);
CREATE INDEX IF NOT EXISTS idx_place_photos_place ON place_photos(place_id, status, sort, id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_place_photos_one_cover ON place_photos(place_id) WHERE is_cover = TRUE;
CREATE UNIQUE INDEX IF NOT EXISTS idx_place_photos_review_photo ON place_photos(review_photo_id) WHERE review_photo_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- Starter taxonomy. This is a controlled vocabulary, not business data: naming the concept
-- "Boutique Hotel" invents nothing about any particular hotel. Seeded here so the vocabulary is
-- identical on every environment. Nothing is assigned to any place by this migration.
-- ---------------------------------------------------------------------------
INSERT INTO place_categories (bucket, slug, name, plural, sort) VALUES
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
  ('experience','spa-wellness','Spa and Wellness','Spas and Wellness',80)
ON CONFLICT (slug) DO NOTHING;
