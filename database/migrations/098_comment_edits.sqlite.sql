-- Migration 098 - comments.updated_at. See the pgsql file.
ALTER TABLE comments ADD COLUMN updated_at TEXT;
