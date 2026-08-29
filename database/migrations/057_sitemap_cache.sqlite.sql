-- Migration 057 (sqlite) - see the pgsql file for why.

CREATE TABLE IF NOT EXISTS sitemap_cache (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    group_key    TEXT NOT NULL,
    part         INTEGER NOT NULL DEFAULT 1,
    xml          TEXT NOT NULL,
    url_count    INTEGER NOT NULL DEFAULT 0,
    generated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS sitemap_cache_key ON sitemap_cache (group_key, part);
CREATE INDEX IF NOT EXISTS sitemap_cache_generated ON sitemap_cache (generated_at);
