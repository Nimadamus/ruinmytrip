-- Migration 125 - seeded cities. See the pgsql file.
ALTER TABLE destinations ADD COLUMN seeded_at TEXT;
CREATE INDEX IF NOT EXISTS idx_destinations_seeded ON destinations (seeded_at) WHERE seeded_at IS NOT NULL;
