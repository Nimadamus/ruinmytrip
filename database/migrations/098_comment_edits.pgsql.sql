-- Migration 098 - when a comment was last edited, so an edit can say so.
ALTER TABLE comments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP;
