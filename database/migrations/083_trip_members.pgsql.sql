-- Collaborative trips. A trip has one owner, in trips.user_id, and any number of members.
--
-- The owner row is NOT duplicated here: one source of ownership means there is no way for the two
-- to disagree, and no way for a bug to leave a trip with two owners or none. Membership is what
-- this table holds, and every member row is somebody the owner invited.
--
-- state: invited, active, declined, left. Rows are kept rather than deleted so that an invitation
-- somebody declined cannot be re-sent every hour, and so a person who left is not silently
-- re-added by a stale form.
CREATE TABLE IF NOT EXISTS trip_members (
    trip_id     INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role        TEXT    NOT NULL DEFAULT 'editor',
    state       TEXT    NOT NULL DEFAULT 'invited',
    invited_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
    decided_at  TIMESTAMP,
    PRIMARY KEY (trip_id, user_id)
);

CREATE INDEX IF NOT EXISTS trip_members_user_idx ON trip_members(user_id, state);
CREATE INDEX IF NOT EXISTS trip_members_trip_idx ON trip_members(trip_id, state);
