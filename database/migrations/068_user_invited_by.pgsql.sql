-- See 068_user_invited_by.sqlite.sql.
ALTER TABLE users ADD COLUMN IF NOT EXISTS invited_by INTEGER REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_users_invited_by ON users (invited_by);
