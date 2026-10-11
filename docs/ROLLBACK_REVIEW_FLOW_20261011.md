# Review flow release: protection and rollback (2026-10-11)

Release: branch `feat/review-flow-20261011` merged to main. Adds migration 107 (`review_drafts`),
the guest review recovery paths, the quick form and the abuse guards.

## Before the deploy

1. **Point in time recovery is available.** Render reports recovery status AVAILABLE for the
   production database (`dpg-d9pek12jnfac73ehlo10-a`, Postgres 16), with a window starting
   2026-10-07 15:46 PDT. Note the exact deploy time; recovery to a moment before it is possible.
2. **A logical export is taken** through the Render API (`POST /postgres/{id}/export`) and its id
   and time are recorded in the deploy report. This is the copy to restore single tables from.
3. **What migration 107 does:** `CREATE TABLE IF NOT EXISTS review_drafts` and one index, inside a
   transaction, plus one ledger row in `schema_migrations`. It touches no existing table and no
   existing row. Verified on Postgres (PGlite 0.5.5 standing in for Postgres 16):
   applies once, a second run applies nothing, `scripts/ci/review_drafts_pg_check.php` passes
   16 of 16 three times in a row (store, hash only, other token reads nothing, expiry, clear,
   rollback file, degrade without the table, migrator recreates it).

## Rollback levels, cheapest first

| Level | When | How | Data effect |
|---|---|---|---|
| 1. Redeploy the previous release | Anything visibly wrong | Render dashboard or API: roll back to the previous deploy (`3a1bd60`, analytics), or `git revert` the merge on main and push | None. The previous code never reads `review_drafts`; the table stays and is ignored. |
| 2. Remove the table | Only to abandon the feature | Run `database/rollback/107_down.sql` (drops `review_drafts`, deletes the ledger row, one transaction) | Loses guest reviews held but not yet saved to an account (they expire in 30 days anyway). Count them first with the query in that file. |
| 3. Point in time recovery | Only if existing data was damaged (not expected: the release writes no existing table) | Render PITR to a moment before the deploy, into a new database, then repoint | Restores everything to that moment. |

Level 1 is the normal rollback and needs no SQL.

## What to watch after the deploy

* Startup log: `applied 107_review_drafts` once, no migrate errors.
* No new PHP warnings, fatals or 500s in the access log.
* `/review/new?place=...` renders the quick form (stars, one box, details folded) on desktop and phone.
* A POST with a stale form token re-renders the form (200, text kept) instead of a 403. This writes
  nothing, so it is safe to check on production.
* A POST with the hidden field filled re-renders with the generic error and writes nothing.
* SEO invariants unchanged on the 26 page snapshot (`~/tools/rmt_ops/prod_check.py`).
* Database counts unchanged (places, cities, members, reviews, trips, posts).

Not done on production without the owner's approval: creating a review, holding a guest review,
or creating an account. Those journeys are covered by `scripts/ci/guest_review_e2e.py` locally.

## Privacy of a held review

* The browser holds a random 48 character token in an HttpOnly, SameSite=Lax cookie (Secure in
  production). The database holds only its SHA-256, so a database read does not reveal a usable token.
* Another browser, or another user, cannot read it: lookup is by the hash of the presented cookie only.
* It expires 30 days after the last write and is swept on the next write; it is deleted the moment
  the review is saved to an account.
* Only the review form's own fields are kept (no CSRF token, no unknown fields), capped in size.
* Shared computer caveat: someone using the same browser before the review is saved would see it
  back in the form for that place. The same is true of the browser's own form autofill.
