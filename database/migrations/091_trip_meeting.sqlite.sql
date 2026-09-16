-- sqlite mirror of 091_trip_meeting.pgsql.sql. See that file for why NULL and 0 are not the same
-- answer, and why a trip that nobody wants to be matched on is still a trip.

ALTER TABLE trips ADD COLUMN travel_style TEXT;
ALTER TABLE trips ADD COLUMN open_to_meeting INTEGER;
CREATE INDEX IF NOT EXISTS idx_trips_meeting ON trips (destination_id, open_to_meeting);
