-- SQLite mirror of 044_warnings_search.pgsql.sql: FTS5 external-content indexes plus the
-- insert/update/delete triggers that keep them in step, matching migration 015's pattern.

CREATE VIRTUAL TABLE IF NOT EXISTS warnings_fts USING fts5(
  title, provider_name, location_detail, body, advice, content='warnings', content_rowid='id'
);
INSERT INTO warnings_fts(rowid, title, provider_name, location_detail, body, advice)
  SELECT id, title, provider_name, location_detail, body, advice FROM warnings;
CREATE TRIGGER IF NOT EXISTS warnings_fts_ai AFTER INSERT ON warnings BEGIN
  INSERT INTO warnings_fts(rowid, title, provider_name, location_detail, body, advice)
    VALUES (new.id, new.title, new.provider_name, new.location_detail, new.body, new.advice);
END;
CREATE TRIGGER IF NOT EXISTS warnings_fts_ad AFTER DELETE ON warnings BEGIN
  INSERT INTO warnings_fts(warnings_fts, rowid, title, provider_name, location_detail, body, advice)
    VALUES('delete', old.id, old.title, old.provider_name, old.location_detail, old.body, old.advice);
END;
CREATE TRIGGER IF NOT EXISTS warnings_fts_au AFTER UPDATE ON warnings BEGIN
  INSERT INTO warnings_fts(warnings_fts, rowid, title, provider_name, location_detail, body, advice)
    VALUES('delete', old.id, old.title, old.provider_name, old.location_detail, old.body, old.advice);
  INSERT INTO warnings_fts(rowid, title, provider_name, location_detail, body, advice)
    VALUES (new.id, new.title, new.provider_name, new.location_detail, new.body, new.advice);
END;

CREATE VIRTUAL TABLE IF NOT EXISTS seo_landing_pages_fts USING fts5(
  h1, meta_description, intro, body, content='seo_landing_pages', content_rowid='id'
);
INSERT INTO seo_landing_pages_fts(rowid, h1, meta_description, intro, body)
  SELECT id, h1, meta_description, intro, body FROM seo_landing_pages;
CREATE TRIGGER IF NOT EXISTS seo_pages_fts_ai AFTER INSERT ON seo_landing_pages BEGIN
  INSERT INTO seo_landing_pages_fts(rowid, h1, meta_description, intro, body)
    VALUES (new.id, new.h1, new.meta_description, new.intro, new.body);
END;
CREATE TRIGGER IF NOT EXISTS seo_pages_fts_ad AFTER DELETE ON seo_landing_pages BEGIN
  INSERT INTO seo_landing_pages_fts(seo_landing_pages_fts, rowid, h1, meta_description, intro, body)
    VALUES('delete', old.id, old.h1, old.meta_description, old.intro, old.body);
END;
CREATE TRIGGER IF NOT EXISTS seo_pages_fts_au AFTER UPDATE ON seo_landing_pages BEGIN
  INSERT INTO seo_landing_pages_fts(seo_landing_pages_fts, rowid, h1, meta_description, intro, body)
    VALUES('delete', old.id, old.h1, old.meta_description, old.intro, old.body);
  INSERT INTO seo_landing_pages_fts(rowid, h1, meta_description, intro, body)
    VALUES (new.id, new.h1, new.meta_description, new.intro, new.body);
END;
