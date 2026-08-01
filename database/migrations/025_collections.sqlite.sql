-- Collections: a traveler-curated ordered list of destinations with an optional note per stop
-- ("Best beaches for solo travelers", "3 cities that ruined me for the right reasons"). Same open
-- UGC model as guides/blog posts. Shipped as its own content type first (CRUD, show/index pages,
-- comments/likes/saves/report, profile listing, sitemap) -- full-text search, tags and the unified
-- feed are deliberately NOT wired in this migration, matching how blog_posts itself landed in
-- stages (base table first, search/feed/tags followed in later migrations).
CREATE TABLE IF NOT EXISTS collections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL, summary TEXT,
  status TEXT NOT NULL DEFAULT 'published', created_at TEXT NOT NULL, updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_collections_status ON collections(status, id);
CREATE TABLE IF NOT EXISTS collection_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  note TEXT, sort INTEGER NOT NULL DEFAULT 0,
  UNIQUE (collection_id, destination_id)
);
CREATE INDEX IF NOT EXISTS idx_collection_items_collection ON collection_items(collection_id, sort, id);
