-- Trip updates.
--
-- A trip is the container this product is built around: dates first, then the things that happen
-- while you are there, then the story afterwards. The middle part had nowhere to live, so a trip
-- page was a title and a body somebody wrote once and never touched again.
--
-- An update is a post. Not a new table: posts already carry photos, hashtags, likes, comments,
-- reposts, moderation and a place in the feed, and a parallel object would have to earn all of that
-- again and would drift from it afterwards.
ALTER TABLE posts ADD COLUMN IF NOT EXISTS trip_id INTEGER REFERENCES trips(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_posts_trip ON posts (trip_id, created_at);
