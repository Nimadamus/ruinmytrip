-- Hashtags/topics: free-form #tags extracted from trip/review/guide/blog text at write time.
-- taggings is a plain polymorphic join (same target_type vocabulary as likes/saves/comments);
-- rows are replaced wholesale on every edit, so no updated_at is needed.
CREATE TABLE IF NOT EXISTS tags (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT UNIQUE NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS taggings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
  target_type TEXT NOT NULL,
  target_id INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE (tag_id, target_type, target_id)
);
CREATE INDEX IF NOT EXISTS idx_taggings_target ON taggings(target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_taggings_tag ON taggings(tag_id);
