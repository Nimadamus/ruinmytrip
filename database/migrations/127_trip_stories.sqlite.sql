-- Migration 127 - trip stories. See the pgsql file.
ALTER TABLE feedback ADD COLUMN topic TEXT;
