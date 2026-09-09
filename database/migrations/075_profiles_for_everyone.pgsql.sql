-- A profile row for every member.
--
-- Registration has created one since profiles existed, but six accounts on the live database
-- predate that. Every profile save is an UPDATE, so for those members editing a profile wrote into
-- nothing and reported success, and the weekly digest could never reach them because it joins users
-- to profiles. The code now creates the row before writing; this gives the existing accounts theirs.
--
-- Idempotent by construction: it only inserts where no row exists, so running it twice does nothing
-- the second time.
INSERT INTO profiles (user_id, display_name, credibility_score)
SELECT u.id, u.username, 0
  FROM users u
 WHERE NOT EXISTS (SELECT 1 FROM profiles p WHERE p.user_id = u.id);
