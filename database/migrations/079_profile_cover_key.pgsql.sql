-- Somewhere to record the stored key of a profile cover image.
--
-- profiles.cover_url has existed since the first schema and nothing could ever set it: the editor
-- offered an avatar and no cover, so every profile fell back to a gradient, and then to the
-- member's own most recent trip photograph once that fallback was written. Uploading a real one
-- needs the same thing the avatar has, a key, so replacing a cover can delete the file it
-- replaced instead of leaving it in storage forever.
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS cover_key TEXT;
