-- Blog: general-topic travel articles (not tied to one destination), distinct from guides
-- (destination-scoped itineraries). Same UGC model as guides: any logged-in, verified user can
-- write one, not staff-only, so it fits the site's existing "travelers write the content" model
-- rather than becoming a company-only channel.
CREATE TABLE IF NOT EXISTS blog_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL, summary TEXT, body TEXT, cover_url TEXT,
  category TEXT NOT NULL DEFAULT 'stories',
  status TEXT NOT NULL DEFAULT 'published', created_at TEXT NOT NULL, updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_blog_posts_status ON blog_posts(status, id);

CREATE VIRTUAL TABLE IF NOT EXISTS blog_posts_fts USING fts5(
  title, summary, body, content='blog_posts', content_rowid='id'
);
INSERT INTO blog_posts_fts(rowid, title, summary, body)
  SELECT id, title, summary, body FROM blog_posts;
CREATE TRIGGER IF NOT EXISTS blog_posts_fts_ai AFTER INSERT ON blog_posts BEGIN
  INSERT INTO blog_posts_fts(rowid, title, summary, body) VALUES (new.id, new.title, new.summary, new.body);
END;
CREATE TRIGGER IF NOT EXISTS blog_posts_fts_ad AFTER DELETE ON blog_posts BEGIN
  INSERT INTO blog_posts_fts(blog_posts_fts, rowid, title, summary, body) VALUES('delete', old.id, old.title, old.summary, old.body);
END;
CREATE TRIGGER IF NOT EXISTS blog_posts_fts_au AFTER UPDATE ON blog_posts BEGIN
  INSERT INTO blog_posts_fts(blog_posts_fts, rowid, title, summary, body) VALUES('delete', old.id, old.title, old.summary, old.body);
  INSERT INTO blog_posts_fts(rowid, title, summary, body) VALUES (new.id, new.title, new.summary, new.body);
END;
