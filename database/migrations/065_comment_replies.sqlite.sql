-- Replying to a reply.
--
-- Comments were a flat list, which works while three people are talking and stops working the
-- moment two conversations happen under the same post: every answer reads as an answer to the
-- post, and nobody can tell who is talking to whom.
--
-- One level deep, deliberately. Threads that nest without limit turn into a tree nobody can read
-- on a phone, and every reply to a reply belongs to the same conversation anyway.
ALTER TABLE comments ADD COLUMN parent_id INTEGER REFERENCES comments(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments (parent_id);
