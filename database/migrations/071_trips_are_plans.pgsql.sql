-- One trip object.
--
-- The site had two things that both meant "I am going to this city": `going`, which is a date
-- range with a visibility setting and no page of its own, and `trips`, which is a story published
-- after the fact. A member had to learn both, neither knew about the other, and the thing the
-- product is actually built around -- an upcoming trip that other travelers can find, follow and
-- meet you on -- existed as neither.
--
-- So a trip now carries the dates. An old story keeps working: its dates are filled from the day
-- it says it happened. Every `going` row becomes a trip, and the `going` table is left in place,
-- untouched and unread, because deleting rows is the one migration you cannot undo.
ALTER TABLE trips ADD COLUMN IF NOT EXISTS date_from DATE;
ALTER TABLE trips ADD COLUMN IF NOT EXISTS date_to   DATE;
-- Same three values `going` used, so nothing about who can see a plan changes in this migration.
ALTER TABLE trips ADD COLUMN IF NOT EXISTS visibility TEXT NOT NULL DEFAULT 'public';

CREATE INDEX IF NOT EXISTS idx_trips_dates ON trips (destination_id, date_from, date_to);
CREATE INDEX IF NOT EXISTS idx_trips_user_dates ON trips (user_id, date_from);

-- A story that says when it happened is a past trip with dates, which is what makes it count
-- towards a traveler's history and appear on a city's timeline.
-- visited_on is TEXT on this schema and date_from is a real DATE, so the assignment needs a cast,
-- and the cast needs the rows filtered first: one row of free text would abort the whole migration.
UPDATE trips SET date_from = visited_on::date, date_to = visited_on::date
 WHERE date_from IS NULL AND visited_on IS NOT NULL
   AND visited_on ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}';

-- Every plan becomes a trip. Titled the way a person would say it, slugged from the source row so
-- the migration is repeatable and cannot collide, and skipped if it is somehow already here.
INSERT INTO trips (user_id, destination_id, title, slug, body, status, visibility,
                   date_from, date_to, created_at)
SELECT g.user_id, g.destination_id,
       d.name || ', ' || TO_CHAR(g.date_from::date, 'FMMon FMDD') || ' to ' || TO_CHAR(g.date_to::date, 'FMMon FMDD YYYY'),
       'plan-' || g.id || '-' || LOWER(REGEXP_REPLACE(d.name, '[^a-zA-Z0-9]+', '-', 'g')),
       '', 'published', g.visibility, g.date_from::date, g.date_to::date, g.created_at
  FROM going g JOIN destinations d ON d.id = g.destination_id
 WHERE NOT EXISTS (
   SELECT 1 FROM trips t
    WHERE t.user_id = g.user_id AND t.destination_id = g.destination_id
      AND t.date_from = g.date_from::date AND t.date_to = g.date_to::date);
