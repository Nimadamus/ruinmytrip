-- Migration 104 - quick join (2026-10-01).
--
-- google_sub: the stable Google account id ("sub" in the ID token) for Sign in with Google. One
-- Google account maps to one member; email can change on Google's side, sub cannot.
-- age_confirmed_at: when a member confirmed they are 16 or older. Joining no longer asks for a
-- date of birth up front; the date is asked for only where it matters (hosting a meetup is 18+).
ALTER TABLE users ADD COLUMN google_sub TEXT;
ALTER TABLE users ADD COLUMN age_confirmed_at TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_sub ON users (google_sub);
