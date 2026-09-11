-- Joining a plan, properly: asking, being answered, and the few things a plan needs before
-- strangers can turn up to it.
--
-- The first cut let somebody ask to join and gave the owner nowhere to answer, which is worse than
-- not asking: the requester waits for a decision that cannot be made. This adds the missing half.
--
--   activity_joins.state gains 'requested' and 'declined'. A decline is remembered rather than
--   deleted, so the same person cannot re-ask into somebody's face every hour, and so the page can
--   honestly tell them what happened.
--   decided_at/decided_by record the answer, for the notification and for moderation.
--
-- And three columns a plan needs once other people can come to it:
--   capacity      how many, in total, the owner is willing to have. Nullable: most plans have no
--                 number and inventing one would be theatre.
--   meeting_point the actual "by the fountain at the top of the steps" line. Shown ONLY to the
--                 owner and to accepted attendees, never on a public page, which is the whole
--                 reason it is a separate column from the notes.
--   end_time      because "drinks from 10" and "drinks 10 to 1" are different invitations.
ALTER TABLE activity_joins ADD COLUMN IF NOT EXISTS decided_at TEXT;
ALTER TABLE activity_joins ADD COLUMN IF NOT EXISTS decided_by INTEGER;

ALTER TABLE trip_activities ADD COLUMN IF NOT EXISTS capacity INTEGER;
ALTER TABLE trip_activities ADD COLUMN IF NOT EXISTS meeting_point TEXT;
ALTER TABLE trip_activities ADD COLUMN IF NOT EXISTS end_time TEXT;
ALTER TABLE trip_activities ADD COLUMN IF NOT EXISTS cancelled_at TEXT;

-- Photographs of a plan: the thing that was eaten, the view from the hike, the ticket stub. Same
-- shape as trip_photos, because the storage layer and the privacy rules are the same and a second
-- shape would be a second set of bugs.
CREATE TABLE IF NOT EXISTS activity_photos (
  id          SERIAL PRIMARY KEY,
  activity_id INTEGER NOT NULL REFERENCES trip_activities(id) ON DELETE CASCADE,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  url         TEXT NOT NULL,
  storage_key TEXT,
  caption     TEXT,
  width       INTEGER,
  height      INTEGER,
  bytes       INTEGER,
  sort        INTEGER NOT NULL DEFAULT 0,
  status      TEXT NOT NULL DEFAULT 'published',
  created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_activity_photos_activity ON activity_photos (activity_id, sort, id);
