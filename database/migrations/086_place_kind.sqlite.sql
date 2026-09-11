-- Keep the provider's own word for what a place is, and map it to a category a reader understands.
--
-- `type` is this site's four coarse buckets and drives the existing landing pages. `category_id`
-- is the fine grained, human category that already existed and which nothing was filling in.
-- `source_kind` is the raw tag the provider used, kept because it is the only way to re-classify
-- later without asking the provider again: a mapping is a decision, and decisions get revised.
ALTER TABLE places ADD COLUMN source_kind TEXT;
CREATE INDEX IF NOT EXISTS idx_places_source_kind ON places(source_kind);

-- A stadium is somewhere a traveler goes on purpose and the taxonomy had no word for it.
-- Filed under experience, which is this site's bucket for a thing you go and do at a time.
INSERT INTO place_categories (bucket, slug, name, plural, sort, status)
SELECT 'experience', 'stadium', 'Stadium', 'Stadiums', 90, 'active'
WHERE NOT EXISTS (SELECT 1 FROM place_categories WHERE slug = 'stadium');
