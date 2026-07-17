-- Phase 2 auth foundation: email verification, password reset, OAuth-ready identity, rate limits.
-- Additive only. Existing accounts get email_verified_at = NULL and are grandfathered
-- (see rmt_email_verified_required()) so nobody is locked out by this migration.

ALTER TABLE users ADD COLUMN email_verified_at TEXT;

-- Verification + reset tokens. The raw token is NEVER stored: only sha256(token). A stolen DB
-- therefore yields no usable links. Single-use (used_at) and short-lived (expires_at).
CREATE TABLE IF NOT EXISTS auth_tokens (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_user ON auth_tokens(user_id, kind);

-- OAuth-ready: no provider wired up yet, but the account model supports linking without a
-- later destructive migration.
CREATE TABLE IF NOT EXISTS oauth_identities (
  provider TEXT NOT NULL,
  provider_uid TEXT NOT NULL,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  PRIMARY KEY (provider, provider_uid)
);

-- Fixed-window rate limiting for login/register/resend/reset. bucket = action + identifier.
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket TEXT NOT NULL,
  window_start BIGINT NOT NULL,
  hits INTEGER NOT NULL DEFAULT 1,
  PRIMARY KEY (bucket, window_start)
);
