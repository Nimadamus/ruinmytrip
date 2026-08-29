-- Migration 056 (sqlite) - see the pgsql file for why.
--
-- SQLite cannot drop a NOT NULL or add a CHECK to a live table, so the table is rebuilt: create
-- the new shape, copy every row, swap. The copy is explicit about its columns rather than
-- SELECT *, because a column added later on one driver and not the other is precisely how these
-- two schemas drift apart.

ALTER TABLE collection_items RENAME TO collection_items_old;

CREATE TABLE collection_items (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  collection_id  INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE CASCADE,
  place_id       INTEGER REFERENCES places(id) ON DELETE CASCADE,
  note           TEXT,
  sort           INTEGER NOT NULL DEFAULT 0,
  -- One or the other, never both, never neither.
  CHECK ((destination_id IS NULL) <> (place_id IS NULL)),
  UNIQUE (collection_id, destination_id)
);

INSERT INTO collection_items (id, collection_id, destination_id, place_id, note, sort)
SELECT id, collection_id, destination_id, NULL, note, sort FROM collection_items_old;

DROP TABLE collection_items_old;

CREATE INDEX IF NOT EXISTS idx_collection_items_collection ON collection_items(collection_id, sort, id);
CREATE UNIQUE INDEX IF NOT EXISTS collection_items_place
    ON collection_items (collection_id, place_id) WHERE place_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS collection_items_place_lookup ON collection_items (place_id);
