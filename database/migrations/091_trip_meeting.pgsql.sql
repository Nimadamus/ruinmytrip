-- Migration 091 - two optional answers on a trip, both of which change who sees it as an offer.
--
-- `travel_style` already exists on a PROFILE and means "how I travel, generally". On a TRIP it
-- means "how I am travelling this time", which is a different fact about the same person: somebody
-- who usually travels with family is on this one alone. The vocabulary is deliberately the same
-- four words (RMT_TRAVEL_STYLES) rather than a second list saying the same thing differently, and
-- a trip that does not answer falls back to the profile.
--
-- `open_to_meeting` is the control that matters. Posting a public trip has always meant the city
-- and the dates are visible; it has never meant "I want to be introduced to strangers", and the
-- product had no way to tell those apart. NULL means unstated, which is how every existing row
-- arrives and is treated exactly as the site behaves today. 0 means somebody said no, and then
-- they are not offered as a match anywhere: not on /matches, not in the near miss list, not as a
-- companion suggestion. The trip itself stays as visible as its own visibility setting says,
-- because hiding a trip somebody chose to make public would be answering a different question
-- than the one they were asked.

ALTER TABLE trips ADD COLUMN IF NOT EXISTS travel_style    TEXT;
ALTER TABLE trips ADD COLUMN IF NOT EXISTS open_to_meeting SMALLINT;
CREATE INDEX IF NOT EXISTS idx_trips_meeting ON trips (destination_id, open_to_meeting);
