-- Migration 090 - one opaque token so a return visit can be counted.
--
-- The event table answers everything about a single visit and nothing at all about a second one.
-- "Did the person who read Bangkok last week come back" and "how many of this month's visitors are
-- new" are the two questions the product most needs answered before deciding what to build, and
-- `journey` cannot answer either of them: it dies with the PHP session.
--
-- So: `visitor`, sixteen random hex characters minted in a first party cookie that survives the
-- session. What it is NOT is as important as what it is:
--
--   * not derived from anything about the person. Not an address, not an agent, not a fingerprint,
--     not a hash of any of those. It is random bytes and nothing else, so it cannot be recomputed
--     from a visitor, only recognised when the same browser sends it back.
--   * not joined to a user id anywhere, because there is no user id in this table to join it to.
--     "Did this account come back" remains a question this table cannot answer, on purpose.
--   * not readable by script (HttpOnly) and not sent to anybody else (SameSite=Lax, first party).
--
-- Clearing cookies resets it, which means "returning visitors" is a floor rather than a true
-- number. That is the honest trade and it is the right way round: the alternative is a durable
-- identifier built out of the person themselves.

ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS visitor TEXT;
CREATE INDEX IF NOT EXISTS idx_contribution_events_visitor ON contribution_events(visitor, created_at);
