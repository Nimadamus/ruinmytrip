# Migration status and tests, 2026-10-11

## Migration 107_review_drafts (live)

What it adds: table `review_drafts` (id, token_hash UNIQUE, place_id, payload, created_at,
updated_at, expires_at) and index `idx_review_drafts_expires`. The server side copy of a guest's
review (app/review_drafts.php): keyed by the sha256 of an HttpOnly cookie token, never readable by
anyone else, deleted when the review is saved, expired after 30 days and swept on the next write.

| Check | Where | Result |
|---|---|---|
| SQLite migration applies, idempotent on rerun | `tests/review_drafts_test.php` (gate) | pass |
| Postgres migration applies and behaves: 16 checks: in the ledger, put and get, only the hash stored, no CSRF token in the payload, another token reads nothing, an expired draft reads nothing, clear deletes, rollback drops the table and the ledger row, nothing throws with the table gone, the migrator recreates it | `scripts/ci/review_drafts_pg_check.php` against a local Postgres stand-in (PGlite 0.5.5); refuses any non local DATABASE_URL | 16 of 16 pass (2026-10-11, before deploy) |
| Real browser journeys that use it (J1 to J10, desktop and phone, both designs) | `scripts/ci/guest_review_e2e.py` | 68 of 68 pass, current and redesigned site (2026-10-11 08:40 to 09:00 PDT) |
| Production apply | deploy dep-db5mlrnlot8c73fg6aq0 (commit 7e154b8), live 03:55 PDT 2026-10-11 | applied once; startup log shows the migration; no errors; review counts unchanged after |
| Rollback written | `database/rollback/107_down.sql` (drops the table, removes the ledger row, in one transaction) | not run in production (not needed) |
| Backup cover | Render point in time recovery for the production database, available from 2026-10-07 | confirmed through the Render API before the deploy |

The local Postgres stand-in was stopped by Claude Code on 2026-10-11 because the computer was low
on memory. All checks above had already passed; it is not needed again unless a new Postgres
migration is written.

## Outstanding production migration requirements

None. No branch prepared on 2026-10-11 adds a migration:

| Branch | Schema change |
|---|---|
| `seo/title-test-revert-20261011` | none |
| `fix/held-review-success-20261011` | none |
| `feat/place-v2-20261011` | none |
| `feat/redesign-v2-20261011` | none (cookie and environment switches only) |
| `seo/sitemap-places-20261011` | none (uses the existing sitemap_cache table) |
| `docs/launch-prep-20261011` | none |

Every deploy still runs `database/migrate.php` from the entrypoint, which applies nothing when the
ledger is current.

## If a future migration is written

1. SQLite and Postgres files side by side (`NNN_name.sqlite.sql`, `NNN_name.pgsql.sql`) plus
   `database/rollback/NNN_down.sql`.
2. Gate test for SQLite; a Postgres check modelled on `scripts/ci/review_drafts_pg_check.php`
   against a local stand-in (start it only for the check, stop it after: this machine runs short of
   memory).
3. Confirm point in time recovery is available before the deploy; record the deploy id and the
   startup log line that shows the migration applied.
