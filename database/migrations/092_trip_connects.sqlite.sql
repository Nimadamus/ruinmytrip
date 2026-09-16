-- sqlite mirror of 092_trip_connects.pgsql.sql. See that file for why this is a request rather
-- than an enrolment, and why the unique index is the idempotency rather than a check in code.

CREATE TABLE IF NOT EXISTS trip_connects (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
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
