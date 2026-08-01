-- Full-text search for collections, same pattern as migration 019 (blog_posts). Collections have
-- no body field (the per-item notes live on collection_items, not the collection row), so this
-- indexes title + summary only.
CREATE VIRTUAL TABLE IF NOT EXISTS collections_fts USING fts5(
  title, summary, content='collections', content_rowid='id'
);
INSERT INTO collections_fts(rowid, title, summary)
  SELECT id, title, summary FROM collections;
CREATE TRIGGER IF NOT EXISTS collections_fts_ai AFTER INSERT ON collections BEGIN
  INSERT INTO collections_fts(rowid, title, summary) VALUES (new.id, new.title, new.summary);
END;
CREATE TRIGGER IF NOT EXISTS collections_fts_ad AFTER DELETE ON collections BEGIN
  INSERT INTO collections_fts(collections_fts, rowid, title, summary) VALUES('delete', old.id, old.title, old.summary);
END;
CREATE TRIGGER IF NOT EXISTS collections_fts_au AFTER UPDATE ON collections BEGIN
  INSERT INTO collections_fts(collections_fts, rowid, title, summary) VALUES('delete', old.id, old.title, old.summary);
  INSERT INTO collections_fts(rowid, title, summary) VALUES (new.id, new.title, new.summary);
END;
