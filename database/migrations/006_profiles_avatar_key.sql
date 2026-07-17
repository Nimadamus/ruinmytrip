-- Object-storage key for the avatar. avatar_url stays as the public URL; avatar_key is what
-- we delete/replace in the bucket. Portable syntax: identical on pgsql/sqlite/mysql.
ALTER TABLE profiles ADD COLUMN avatar_key TEXT;
