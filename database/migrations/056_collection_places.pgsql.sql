-- Migration 056 - a travel list can hold places, not only cities.
--
-- Collections already exist and work: create, edit, reorder, publish, comment, like, save. What
-- they hold is a destination. So "Weekend in New York" is expressible and "Favorite restaurants in
-- Paris" is not, which is most of what a traveler actually wants to make a list of.
--
-- The fix is one column on the table that already exists, not a second lists system beside it. A
-- parallel place-list feature would mean two of everything -- two permission checks, two comment
-- targets, two sets of privacy rules, two things to keep in step -- and the two would drift.
--
-- destination_id becomes nullable and place_id joins it, with exactly one of the two set per row.
-- Existing rows already satisfy that, so nothing needs rewriting and nothing is at risk: this
-- migration only widens what is allowed.

ALTER TABLE collection_items ADD COLUMN IF NOT EXISTS place_id INTEGER REFERENCES places(id) ON DELETE CASCADE;
ALTER TABLE collection_items ALTER COLUMN destination_id DROP NOT NULL;

-- One or the other, never both, never neither. Enforced in the database because an item that is
-- somehow neither would render as a blank row on a public page, and a check constraint is cheaper
-- than the code that would have to defend every read.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'collection_items_one_target') THEN
        ALTER TABLE collection_items ADD CONSTRAINT collection_items_one_target
            CHECK ((destination_id IS NULL) <> (place_id IS NULL));
    END IF;
END $$;

-- The old uniqueness was UNIQUE (collection_id, destination_id), which stops one city appearing
-- twice in a list. Places need the same protection, and a partial index gives it without
-- disturbing the existing constraint.
CREATE UNIQUE INDEX IF NOT EXISTS collection_items_place
    ON collection_items (collection_id, place_id) WHERE place_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS collection_items_place_lookup ON collection_items (place_id);
