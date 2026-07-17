-- sqlite mirror of 002 (SERIAL -> INTEGER PRIMARY KEY AUTOINCREMENT, BIGINT -> INTEGER).
ALTER TABLE users ADD COLUMN email_verified_at TEXT;

CREATE TABLE IF NOT EXISTS auth_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_user ON auth_tokens(user_id, kind);

CREATE TABLE IF NOT EXISTS oauth_identities (
  provider TEXT NOT NULL,
  provider_uid TEXT NOT NULL,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  PRIMARY KEY (provider, provider_uid)
);

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket TEXT NOT NULL,
  window_start INTEGER NOT NULL,
  hits INTEGER NOT NULL DEFAULT 1,
  PRIMARY KEY (bucket, window_start)
);
