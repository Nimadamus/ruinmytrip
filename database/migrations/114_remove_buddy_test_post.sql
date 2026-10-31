-- The live buddy check of 2026-10-30 posted one labelled test post from the owner's account. The
-- browser sign in needed to remove it through the page froze, so it is removed here, matched on
-- both its id and its exact title so nothing else can be touched.
UPDATE buddy_posts SET status = 'removed' WHERE id = 1 AND title = 'Site team test post, please ignore';
