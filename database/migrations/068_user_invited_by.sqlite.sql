-- Who brought this member here. Set once at signup from a personal invite link (?ref=username),
-- never edited after. See app/invites.php.
ALTER TABLE users ADD COLUMN invited_by INTEGER REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_users_invited_by ON users (invited_by);
