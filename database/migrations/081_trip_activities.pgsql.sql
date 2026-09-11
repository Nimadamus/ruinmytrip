-- What a traveler is actually doing there.
--
-- The site can say who is going to Lisbon, when, and why they are worth meeting. It cannot say what
-- any of them are doing, which is the half of the question people actually arrive with, and it is
-- the half that turns two overlapping date ranges into a reason to meet: "dinner in Alfama on
-- Friday" is something another traveler can answer.
--
-- Shape notes:
--   * user_id and destination_id are denormalised from the trip. The first is what every like and
--     comment target on this site needs; the second is what makes "what is happening in Lisbon
--     that week" one index scan instead of a join through trips on every city page.
--   * day is nullable. "Sintra at some point" is a real plan and forcing a date on it is how
--     itinerary tools turn into spreadsheets nobody fills in.
--   * visibility is 'trip' or 'private'. An activity can be MORE private than the trip it belongs
--     to and never less: a public trip can hide one dinner, a private trip can never leak one.
--   * join_mode says whether another traveler may say they are coming. Default is 'no', because
--     somebody planning dinner has not invited the internet.
--   * done/rating/recommend are the post-trip half: the same row becomes the answer to "how was
--     it", which is what makes planning worth anything to the next person.
CREATE TABLE IF NOT EXISTS trip_activities (
  id             SERIAL PRIMARY KEY,
  trip_id        INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  day            DATE,
  start_time     TEXT,
  title          TEXT NOT NULL,
  category       TEXT NOT NULL DEFAULT 'other',
  place_id       INTEGER REFERENCES places(id) ON DELETE SET NULL,
  location_text  TEXT,
  notes          TEXT,
  link           TEXT,
  photo_url      TEXT,
  storage_key    TEXT,
  visibility     TEXT NOT NULL DEFAULT 'trip',
  join_mode      TEXT NOT NULL DEFAULT 'no',
  done           SMALLINT NOT NULL DEFAULT 0,
  rating         SMALLINT,
  recommend      SMALLINT,
  sort           INTEGER NOT NULL DEFAULT 0,
  status         TEXT NOT NULL DEFAULT 'published',
  created_at     TEXT NOT NULL,
  updated_at     TEXT
);
CREATE INDEX IF NOT EXISTS idx_activities_trip ON trip_activities (trip_id, day, start_time, sort);
CREATE INDEX IF NOT EXISTS idx_activities_dest_day ON trip_activities (destination_id, day);
CREATE INDEX IF NOT EXISTS idx_activities_user ON trip_activities (user_id);

-- Who else is coming. One row per person per activity; 'interested' and 'going' are the two states
-- somebody can be in, and no row at all is the third.
CREATE TABLE IF NOT EXISTS activity_joins (
  activity_id INTEGER NOT NULL REFERENCES trip_activities(id) ON DELETE CASCADE,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  state       TEXT NOT NULL DEFAULT 'interested',
  created_at  TEXT NOT NULL,
  PRIMARY KEY (activity_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_activity_joins_user ON activity_joins (user_id);
