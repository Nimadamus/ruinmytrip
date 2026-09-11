-- Somewhere to record the stored key of a profile cover image. See the pgsql file.
ALTER TABLE profiles ADD COLUMN cover_key TEXT;
