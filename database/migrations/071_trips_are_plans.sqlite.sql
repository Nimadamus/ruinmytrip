-- See the pgsql version for why this exists. SQLite has no ADD COLUMN IF NOT EXISTS, and the
-- migrator runs each file exactly once, so plain ADD COLUMN is correct here.
ALTER TABLE trips ADD COLUMN date_from TEXT;
ALTER TABLE trips ADD COLUMN date_to   TEXT;
ALTER TABLE trips ADD COLUMN visibility TEXT NOT NULL DEFAULT 'public';

CREATE INDEX IF NOT EXISTS idx_trips_dates ON trips (destination_id, date_from, date_to);
CREATE INDEX IF NOT EXISTS idx_trips_user_dates ON trips (user_id, date_from);

UPDATE trips SET date_from = visited_on, date_to = visited_on
 WHERE date_from IS NULL AND visited_on IS NOT NULL;

-- Titles are built with the dates as stored (YYYY-MM-DD) rather than reformatted: SQLite has no
-- month-name formatter, and a title that reads differently on the two drivers would be a difference
-- nobody could explain later. The trip page renders dates properly either way.
INSERT INTO trips (user_id, destination_id, title, slug, body, status, visibility,
                   date_from, date_to, created_at)
SELECT g.user_id, g.destination_id,
       d.name || ', ' || g.date_from || ' to ' || g.date_to,
       'plan-' || g.id || '-' || LOWER(REPLACE(d.name, ' ', '-')),
       '', 'published', g.visibility, g.date_from, g.date_to, g.created_at
  FROM going g JOIN destinations d ON d.id = g.destination_id
 WHERE NOT EXISTS (
   SELECT 1 FROM trips t
    WHERE t.user_id = g.user_id AND t.destination_id = g.destination_id
      AND t.date_from = g.date_from AND t.date_to = g.date_to);
