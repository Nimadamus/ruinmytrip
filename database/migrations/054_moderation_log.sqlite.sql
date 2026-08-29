-- sqlite mirror of 054_moderation_log.pgsql.sql. See that file for the rationale: one row per
-- moderation action, written by the single function that performs one, so an action that is not
-- logged is an action that did not happen.

CREATE TABLE IF NOT EXISTS moderation_log (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  target_type TEXT NOT NULL,
  target_id   INTEGER NOT NULL,
  report_id   INTEGER REFERENCES reports(id) ON DELETE SET NULL,
  action      TEXT NOT NULL,
  from_status TEXT,
  to_status   TEXT,
  note        TEXT,
  created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_moderation_log_target ON moderation_log(target_type, target_id, created_at);
CREATE INDEX IF NOT EXISTS idx_moderation_log_actor ON moderation_log(actor_id, created_at);
