-- Saving a place.
--
-- No new table: `saves` is already the polymorphic bucket every other save uses
-- (target_type = trip | review | guide | blog_post | collection | destination), and its
-- PRIMARY KEY (user_id, target_type, target_id) makes a duplicate save impossible at the
-- database level rather than by hoping the application checked first. Places join that
-- vocabulary as target_type = 'place'.
--
-- Two things were missing for a save to be readable at scale:
--
-- `created_at` so a personal /saved page can show the most recently collected first. It is
-- nullable and left NULL on the rows that already exist -- we do not know when those were
-- saved and inventing a timestamp would be a fabricated fact. Ordering coalesces NULL to ''
-- so those rows sort last instead of randomly.
--
-- An index on (target_type, target_id): every existing save COUNT filters exactly that way
-- and the primary key is useless for it (its leading column is user_id), so each count was a
-- full scan of the table.
ALTER TABLE saves ADD COLUMN created_at TEXT;
CREATE INDEX IF NOT EXISTS idx_saves_target ON saves(target_type, target_id);
