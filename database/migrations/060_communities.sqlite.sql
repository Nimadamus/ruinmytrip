-- Migration 060 (sqlite) - see the pgsql file for why.

ALTER TABLE collections ADD COLUMN join_policy TEXT NOT NULL DEFAULT 'closed';
ALTER TABLE collections ADD COLUMN members_can_add INTEGER NOT NULL DEFAULT 0;

ALTER TABLE collection_items ADD COLUMN added_by INTEGER REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE collection_items ADD COLUMN pinned INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS collection_members (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role          TEXT NOT NULL DEFAULT 'member',
  status        TEXT NOT NULL DEFAULT 'active',
  joined_at     TEXT NOT NULL,
  removed_at    TEXT,
  CHECK (role IN ('owner','member')),
  CHECK (status IN ('active','removed')),
  UNIQUE (collection_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_collection_members_collection ON collection_members(collection_id, status);
CREATE INDEX IF NOT EXISTS idx_collection_members_user ON collection_members(user_id, status);

CREATE TABLE IF NOT EXISTS collection_invites (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
  token         TEXT NOT NULL UNIQUE,
  created_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at    TEXT NOT NULL,
  revoked_at    TEXT
);

CREATE INDEX IF NOT EXISTS idx_collection_invites_collection ON collection_invites(collection_id, revoked_at);

-- Every collection that already exists gets its owner as a member row, so member counting has one
-- shape and not two. Without this an old list reads as a group with zero members, which is both
-- wrong and the sort of thing that only shows up once a page is live.
INSERT INTO collection_members (collection_id, user_id, role, status, joined_at)
SELECT c.id, c.user_id, 'owner', 'active', COALESCE(c.created_at, '2026-01-01')
  FROM collections c
 WHERE NOT EXISTS (SELECT 1 FROM collection_members m WHERE m.collection_id = c.id AND m.user_id = c.user_id);

-- Items that predate membership were all added by the owner. Leaving added_by null would render
-- as an unattributed row next to attributed ones.
UPDATE collection_items
   SET added_by = (SELECT c.user_id FROM collections c WHERE c.id = collection_items.collection_id)
 WHERE added_by IS NULL;
