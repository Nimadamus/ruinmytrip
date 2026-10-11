-- Manual rollback for migration 107 (review_drafts). NOT run by the migrator.
--
-- Normal rollback is a redeploy of the previous release (docs/ROLLBACK_REVIEW_FLOW_20261011.md).
-- The previous code never reads review_drafts, so the table can stay where it is and nothing
-- breaks. Run this only to remove the table for good.
--
-- What it destroys: guest reviews that were written, held for the account step, and not yet saved
-- to an account. Rows expire after 30 days anyway. Nothing else references this table, and no
-- member's published or held review lives in it (those are in reviews).
--
-- Count first, so the loss is known:
--     SELECT COUNT(*) FROM review_drafts WHERE expires_at >= to_char(now(), 'YYYY-MM-DD HH24:MI:SS');
--
-- Both statements in one transaction, so the ledger and the table never disagree. Removing the
-- ledger row lets a later deploy of the review branch create the table again.
BEGIN;
DROP TABLE IF EXISTS review_drafts;
DELETE FROM schema_migrations WHERE version = '107_review_drafts';
COMMIT;
