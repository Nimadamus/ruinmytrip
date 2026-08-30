-- A post can be about one specific place, not only a city.
--
-- The review inventory and the conversation were two separate worlds: you could review the Anne
-- Frank House or ask a question about Amsterdam, and there was nowhere to ask a question about the
-- Anne Frank House. That question is the most common thing a traveler actually types, and the
-- answer belongs on the page about the place.
ALTER TABLE posts ADD COLUMN place_id INTEGER REFERENCES places(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_posts_place ON posts (place_id, status);
