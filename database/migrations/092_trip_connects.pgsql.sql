-- Migration 092 - saying you would like to meet somebody, as an object rather than a message.
--
-- The first thing one traveler does to another on this site used to be either nothing or a private
-- message to a stranger. Both are wrong: nothing is why a match list goes cold, and an unsolicited
-- message is how a travel site turns into somewhere women stop using. So there is a third thing,
-- and it is deliberately small: I am interested in meeting around this trip.
--
-- What makes it safe is what it is NOT. It carries no words, so it cannot be abuse with a message
-- attached. It exposes no address, no telephone number and no email; both sides already have the
-- only thing it shares, which is a city and a date range they both published. And it is a request
-- rather than an enrolment: the other person decides, and until they do, nothing about them has
-- changed.
--
-- One row per (trip, sender). The unique index is the idempotency: a double tap, a refresh, a
-- retried request and a back button all land on the row that is already there.
--
-- States: 'interested' is the ask, 'accepted' is both sides saying yes, 'declined' is a no that is
-- kept rather than deleted so the same person cannot ask again the next day, and 'withdrawn' is
-- the sender changing their mind. A decision is never silently reversed by anybody but its owner.

CREATE TABLE IF NOT EXISTS trip_connects (
  id           SERIAL PRIMARY KEY,
  trip_id      INTEGER NOT NULL,
  from_user_id INTEGER NOT NULL,
  to_user_id   INTEGER NOT NULL,
  state        TEXT NOT NULL DEFAULT 'interested',
  created_at   TEXT NOT NULL,
  decided_at   TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_trip_connects_once ON trip_connects (trip_id, from_user_id);
CREATE INDEX IF NOT EXISTS idx_trip_connects_to ON trip_connects (to_user_id, state);
CREATE INDEX IF NOT EXISTS idx_trip_connects_from ON trip_connects (from_user_id, state);
