-- Migration 054 - a record of why content disappeared.
--
-- Moderation already worked: a report created a queue item, a moderator could hide or restore, and
-- nothing was ever removed by report volume alone. What was missing was the answer to the question
-- somebody always asks afterwards -- who did that, when, and why. Without it, a hidden review is
-- indistinguishable from a bug, an argument about a decision has no facts in it, and a moderator
-- cannot review their own past calls.
--
-- One row per action, written by the single function that performs moderation, so an action that
-- is not logged is an action that did not happen. It records the status it moved FROM as well as
-- to, because "restored" only means something if you know what it was restored from.
--
-- This is a log, not a courtroom. No appeals, no state machine, no workflow: an entry, a reason,
-- and the person who made the call.

CREATE TABLE IF NOT EXISTS moderation_log (
  id          SERIAL PRIMARY KEY,
  actor_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  target_type TEXT NOT NULL,
  target_id   INTEGER NOT NULL,
  report_id   INTEGER REFERENCES reports(id) ON DELETE SET NULL,
  action      TEXT NOT NULL,          -- dismiss | hide | remove | restore
  from_status TEXT,
  to_status   TEXT,
  note        TEXT,
  created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_moderation_log_target ON moderation_log(target_type, target_id, created_at);
CREATE INDEX IF NOT EXISTS idx_moderation_log_actor ON moderation_log(actor_id, created_at);
