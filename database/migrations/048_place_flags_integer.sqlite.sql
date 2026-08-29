-- SQLite counterpart to 048_place_flags_integer.pgsql.sql.
--
-- Nothing to do: 047 already declared place_hours.closed and place_photos.is_cover as INTEGER
-- here, and the CHECK constraints already compare against 0 and 1. The Postgres file is the one
-- that changes, because it declared them BOOLEAN and `is_cover = 1` is not a valid comparison
-- there.
--
-- This file exists so schema_migrations holds the same version list on both drivers. A migration
-- present for one driver and absent for the other is skipped and reported by the runner, which is
-- supported behaviour but makes two environments harder to compare at a glance.
--
-- The IN (0,1) guard the Postgres table now carries is enforced here by rmt_place_set_hours() and
-- rmt_place_photo_add(), which are the only writers and which normalise to 0 or 1 before binding.

SELECT 1;
