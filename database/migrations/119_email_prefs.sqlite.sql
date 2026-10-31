-- Migration 119 - email preferences. See the pgsql file.
ALTER TABLE profiles ADD COLUMN email_muted TEXT;
