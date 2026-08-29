-- Migration 057 - somewhere to keep a generated sitemap.
--
-- /sitemap.xml ran about twenty unbounded queries on every request and rendered the result inline:
-- 575 URLs in ~400ms. That is fine and it does not stay fine. The queries have no LIMIT and no
-- shared plan, so the cost grows with the site, and a crawler fetching the index plus every child
-- would pay it once per file.
--
-- The fix is boring on purpose: render each child sitemap once, keep the XML, serve it until it
-- goes stale or something changes. One row per (group, part). No queue, no workers, no external
-- cache -- a table the application already has a connection to.
--
-- url_count is stored because the sitemap index needs to know which parts exist without parsing
-- the XML back out, and because "how many places are indexable" is a question worth being able to
-- answer with a SELECT.

CREATE TABLE IF NOT EXISTS sitemap_cache (
    id           SERIAL PRIMARY KEY,
    group_key    TEXT NOT NULL,
    part         INTEGER NOT NULL DEFAULT 1,
    xml          TEXT NOT NULL,
    url_count    INTEGER NOT NULL DEFAULT 0,
    generated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS sitemap_cache_key ON sitemap_cache (group_key, part);
CREATE INDEX IF NOT EXISTS sitemap_cache_generated ON sitemap_cache (generated_at);
