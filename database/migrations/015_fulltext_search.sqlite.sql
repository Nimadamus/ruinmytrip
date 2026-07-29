CREATE VIRTUAL TABLE IF NOT EXISTS destinations_fts USING fts5(
  name, country, summary, content='destinations', content_rowid='id'
);
INSERT INTO destinations_fts(rowid, name, country, summary)
  SELECT id, name, country, summary FROM destinations;
CREATE TRIGGER IF NOT EXISTS destinations_fts_ai AFTER INSERT ON destinations BEGIN
  INSERT INTO destinations_fts(rowid, name, country, summary) VALUES (new.id, new.name, new.country, new.summary);
END;
CREATE TRIGGER IF NOT EXISTS destinations_fts_ad AFTER DELETE ON destinations BEGIN
  INSERT INTO destinations_fts(destinations_fts, rowid, name, country, summary) VALUES('delete', old.id, old.name, old.country, old.summary);
END;
CREATE TRIGGER IF NOT EXISTS destinations_fts_au AFTER UPDATE ON destinations BEGIN
  INSERT INTO destinations_fts(destinations_fts, rowid, name, country, summary) VALUES('delete', old.id, old.name, old.country, old.summary);
  INSERT INTO destinations_fts(rowid, name, country, summary) VALUES (new.id, new.name, new.country, new.summary);
END;

CREATE VIRTUAL TABLE IF NOT EXISTS reviews_fts USING fts5(
  title, subject_name, body, content='reviews', content_rowid='id'
);
INSERT INTO reviews_fts(rowid, title, subject_name, body)
  SELECT id, title, subject_name, body FROM reviews;
CREATE TRIGGER IF NOT EXISTS reviews_fts_ai AFTER INSERT ON reviews BEGIN
  INSERT INTO reviews_fts(rowid, title, subject_name, body) VALUES (new.id, new.title, new.subject_name, new.body);
END;
CREATE TRIGGER IF NOT EXISTS reviews_fts_ad AFTER DELETE ON reviews BEGIN
  INSERT INTO reviews_fts(reviews_fts, rowid, title, subject_name, body) VALUES('delete', old.id, old.title, old.subject_name, old.body);
END;
CREATE TRIGGER IF NOT EXISTS reviews_fts_au AFTER UPDATE ON reviews BEGIN
  INSERT INTO reviews_fts(reviews_fts, rowid, title, subject_name, body) VALUES('delete', old.id, old.title, old.subject_name, old.body);
  INSERT INTO reviews_fts(rowid, title, subject_name, body) VALUES (new.id, new.title, new.subject_name, new.body);
END;

CREATE VIRTUAL TABLE IF NOT EXISTS trips_fts USING fts5(
  title, body, content='trips', content_rowid='id'
);
INSERT INTO trips_fts(rowid, title, body)
  SELECT id, title, body FROM trips;
CREATE TRIGGER IF NOT EXISTS trips_fts_ai AFTER INSERT ON trips BEGIN
  INSERT INTO trips_fts(rowid, title, body) VALUES (new.id, new.title, new.body);
END;
CREATE TRIGGER IF NOT EXISTS trips_fts_ad AFTER DELETE ON trips BEGIN
  INSERT INTO trips_fts(trips_fts, rowid, title, body) VALUES('delete', old.id, old.title, old.body);
END;
CREATE TRIGGER IF NOT EXISTS trips_fts_au AFTER UPDATE ON trips BEGIN
  INSERT INTO trips_fts(trips_fts, rowid, title, body) VALUES('delete', old.id, old.title, old.body);
  INSERT INTO trips_fts(rowid, title, body) VALUES (new.id, new.title, new.body);
END;

CREATE VIRTUAL TABLE IF NOT EXISTS guides_fts USING fts5(
  title, summary, body, content='guides', content_rowid='id'
);
INSERT INTO guides_fts(rowid, title, summary, body)
  SELECT id, title, summary, body FROM guides;
CREATE TRIGGER IF NOT EXISTS guides_fts_ai AFTER INSERT ON guides BEGIN
  INSERT INTO guides_fts(rowid, title, summary, body) VALUES (new.id, new.title, new.summary, new.body);
END;
CREATE TRIGGER IF NOT EXISTS guides_fts_ad AFTER DELETE ON guides BEGIN
  INSERT INTO guides_fts(guides_fts, rowid, title, summary, body) VALUES('delete', old.id, old.title, old.summary, old.body);
END;
CREATE TRIGGER IF NOT EXISTS guides_fts_au AFTER UPDATE ON guides BEGIN
  INSERT INTO guides_fts(guides_fts, rowid, title, summary, body) VALUES('delete', old.id, old.title, old.summary, old.body);
  INSERT INTO guides_fts(rowid, title, summary, body) VALUES (new.id, new.title, new.summary, new.body);
END;
