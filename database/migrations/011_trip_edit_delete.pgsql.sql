-- Trips could be created but never edited or deleted -- no trip_edit/trip_delete route existed,
-- unlike reviews which have had both since migration 003. Adding updated_at so edits can be
-- timestamped the same way reviews are; status already supports a 'removed' soft-delete value.
ALTER TABLE trips ADD COLUMN IF NOT EXISTS updated_at TEXT;
