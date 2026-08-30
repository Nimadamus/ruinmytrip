-- Reposting: how something somebody wrote reaches people who have never heard of them.
--
-- Everything published here so far only reached the author's own followers and whoever happened to
-- open the right page. A member who reads something good has no way to put it in front of their
-- own followers, which is the mechanism that makes a social network compound rather than a
-- collection of separate audiences.
--
-- A repost is a post: same table, same comments, same moderation, same everything, with a pointer
-- to what it is passing on. An empty body is a plain repost; a body makes it a quote.
ALTER TABLE posts ADD COLUMN repost_of INTEGER REFERENCES posts(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_posts_repost ON posts (repost_of);
