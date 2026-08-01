-- Full-text search for collections, same pattern as migration 019 (blog_posts). Collections have
-- no body field (the per-item notes live on collection_items, not the collection row), so this
-- indexes title + summary only.
ALTER TABLE collections ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(summary,'')), 'B')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_collections_search ON collections USING GIN (search_vector);
