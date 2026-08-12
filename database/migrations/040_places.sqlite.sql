-- Places: the thing a review is actually about. See 040_places.pgsql.sql for the full rationale.
CREATE TABLE IF NOT EXISTS places (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  slug TEXT UNIQUE NOT NULL,
  name TEXT NOT NULL,
  name_key TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'attraction',
  created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_places_dest_namekey ON places(destination_id, name_key);
CREATE INDEX IF NOT EXISTS idx_places_dest ON places(destination_id, type, id);

ALTER TABLE reviews ADD COLUMN place_id INTEGER REFERENCES places(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_reviews_place ON reviews(place_id, status);

CREATE VIRTUAL TABLE IF NOT EXISTS places_fts USING fts5(
  name, type, content='places', content_rowid='id'
);
INSERT INTO places_fts(rowid, name, type) SELECT id, name, type FROM places;
CREATE TRIGGER IF NOT EXISTS places_fts_ai AFTER INSERT ON places BEGIN
  INSERT INTO places_fts(rowid, name, type) VALUES (new.id, new.name, new.type);
END;
CREATE TRIGGER IF NOT EXISTS places_fts_ad AFTER DELETE ON places BEGIN
  INSERT INTO places_fts(places_fts, rowid, name, type) VALUES('delete', old.id, old.name, old.type);
END;
CREATE TRIGGER IF NOT EXISTS places_fts_au AFTER UPDATE ON places BEGIN
  INSERT INTO places_fts(places_fts, rowid, name, type) VALUES('delete', old.id, old.name, old.type);
  INSERT INTO places_fts(rowid, name, type) VALUES (new.id, new.name, new.type);
END;
