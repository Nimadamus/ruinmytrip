-- SQLite mirror of 047_summary_provenance.pgsql.sql. See that file for the rationale.
-- SQLite has no ADD COLUMN IF NOT EXISTS; these columns are new in this migration and the
-- migrator applies each version exactly once, so a plain ADD COLUMN is safe.

ALTER TABLE destinations ADD COLUMN summary_reviewed_at TEXT;
ALTER TABLE destinations ADD COLUMN summary_sources TEXT;
