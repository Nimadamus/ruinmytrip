-- Indexes for the paths the social layer added this week.
--
-- Every one of these is a query that now runs on a page somebody loads, not a report: the feed
-- scope asks "which cities has this member saved", the trip visibility check asks "does this
-- person follow that one", the city pages ask for comments by target, and every city notification
-- checks whether it has already been sent. They are all fine at a hundred members and all of them
-- are sequential scans at a hundred thousand.
--
-- Nothing here changes behaviour, so it is safe to apply at any time; IF NOT EXISTS because some
-- of these may already exist under another name.

-- The feed scope subquery: this member's saved cities.
CREATE INDEX IF NOT EXISTS idx_saves_user_type ON saves (user_id, target_type);

-- "Does A follow B", asked by trip visibility, the plan visibility clause and follower counts.
-- The primary key covers (follower_id, followee_id); this is the other direction.
CREATE INDEX IF NOT EXISTS idx_follows_followee ON follows (followee_id, follower_id);

-- Comments are read by target on every place, review, trip, post and meetup page.
CREATE INDEX IF NOT EXISTS idx_comments_target ON comments (target_type, target_id, status);

-- The dedupe every city notification does before writing: have we told this person about this
-- thing already.
CREATE INDEX IF NOT EXISTS idx_notifications_dedupe
    ON notifications (user_id, type, target_type, target_id);

-- Who is going to this city and has not left yet, which is the query behind every city page, the
-- home page row and the share cards.
CREATE INDEX IF NOT EXISTS idx_trips_public_upcoming
    ON trips (destination_id, date_to, visibility, status);
