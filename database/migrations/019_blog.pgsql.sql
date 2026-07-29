-- Blog: general-topic travel articles (not tied to one destination), distinct from guides
-- (destination-scoped itineraries). Same UGC model as guides: any logged-in, verified user can
-- write one, not staff-only, so it fits the site's existing "travelers write the content" model
-- rather than becoming a company-only channel.
CREATE TABLE IF NOT EXISTS blog_posts (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL, summary TEXT, body TEXT, cover_url TEXT,
  category TEXT NOT NULL DEFAULT 'stories',
  status TEXT NOT NULL DEFAULT 'published', created_at TEXT NOT NULL, updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_blog_posts_status ON blog_posts(status, id);

ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS search_vector tsvector
  GENERATED ALWAYS AS (
    setweight(to_tsvector('english', coalesce(title,'')), 'A') ||
    setweight(to_tsvector('english', coalesce(summary,'')), 'B') ||
    setweight(to_tsvector('english', coalesce(body,'')), 'C')
  ) STORED;
CREATE INDEX IF NOT EXISTS idx_blog_posts_search ON blog_posts USING GIN (search_vector);
