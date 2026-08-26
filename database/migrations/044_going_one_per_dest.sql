-- One upcoming plan per traveler per destination. Re-sharing the same city updates the dates
-- rather than stacking duplicate rows on Who's going.
CREATE UNIQUE INDEX IF NOT EXISTS idx_going_user_dest ON going (user_id, destination_id);
