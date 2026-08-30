-- One picture per post.
--
-- Text-only was the right place to start and the wrong place to stop: half of what travelers say
-- to each other is "look at this". One image, not a gallery -- a post is a remark, and a remark
-- with six photos attached is a trip story that belongs in trip_photos.
ALTER TABLE posts ADD COLUMN image_url TEXT;
ALTER TABLE posts ADD COLUMN image_key TEXT;
ALTER TABLE posts ADD COLUMN image_w INTEGER;
ALTER TABLE posts ADD COLUMN image_h INTEGER;
