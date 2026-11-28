# Phase 1 release package (2026-11-27)

Branch `feat/phase1-utility-1127`, successor of `b14ca75`. NOT merged, NOT deployed, no production
migration run, automatic trap alert emails OFF. Everything below was run locally against copies of
the dev database with mail disabled (`RESEND_API_KEY` blank).

## Preview

There is no hosted preview. The site deploys from main, and no staging host exists for this
branch. The QA ran on two local servers started from the same database copy:
main `111ea0b` on 127.0.0.1:8101 and this branch on 127.0.0.1:8102. Screenshots stand in for the preview.
Script: `C:\Users\BL\rmt_research\release_1127\serve.py`. Shots: `...\release_1127\shots\`.

## Features

1. Home: new H1 and lede, search, five top destination pills, value strip, a "Know before you go" grid. The Santorini hero is unchanged and every existing section and link is kept.
2. City page warnings block: five tabs with real counts, honest cost badges, Helpful and Saved Me votes with no account needed, and Save to Trip Checklist.
3. Save, then signup: a dialog with the exact copy. The save waits through signup and lands on `/saved#checklist`.
4. Three step trap report: no account needed. It goes into the editor queue, and nothing shows until an editor publishes it.
5. Trap alert emails: when an editor publishes a report, members with that city on their checklist get one email per city per run. Automatic sending is OFF.
6. Founding Traveler: a report never grants it. The badge still needs a published review.

## SEO protection (before/after)

Default mode keeps all existing URLs, canonicals, sitemap entries, robots rules and internal links.
The two SEO changes from the plan are built but are behind flags that are OFF.

| Check | Main | Branch (default) | With the unapproved flags |
|---|---|---|---|
| Home internal links | 135 | 137 (adds "More destinations" chips) | 93 (`RMT_P1_TRIM_LINKS=1`, 44 lost) |
| Sitewide nav and footer links | baseline | identical | Talk, Who's going, Meetups, Collections, buddies and more removed |
| City page links lost | n/a | 4. All are robots-disallowed or nofollow (report and save actions), so no crawlable link is lost | more |
| City page text shared with `/warnings/{city}` | Prague 4.1%, Amsterdam 4.2% | at or below main (Lisbon 10.8%, Bangkok 8.9%, Rome 10.9%) | about 30 to 36% (`RMT_P1_FULL_SCAMS=1`) |
| Canonical, robots meta, titles | baseline | identical on every page checked | identical |
| robots.txt, sitemap index, 7 sub-sitemaps | baseline | byte identical (md5) | identical |

SEO implications of the flags:
- **TRIM_LINKS:** this removes sitewide links to the community hubs. That weakens how those pages are crawled and how much link equity they get. Their URLs stay live and stay in the sitemap.
- **FULL_SCAMS:** this repeats each indexable `/warnings/{city}` page on the city page. That risks the two pages competing for the same queries, and a weaker canonical signal.

Recommendation: keep both OFF. Revisit them separately with Search Console data.

## Migrations 135 and 136

- **135 `warning_votes`, `checklist_saves`:** new tables only. They have a unique vote key, a CHECK on kind, and ON DELETE CASCADE from users.
- **136 `trap_alerts_sent`:** a new table with a primary key on (user, story) and ON DELETE CASCADE.
- **Nothing existing is altered.** Both migrations come in sqlite and pgsql versions. The migrator is forward only, tracked in `schema_migrations`, and runs on deploy (`php database/migrate.php`).
- **Postgres test (a local Postgres server):**
  - Ran main's migrations, then added sample rows.
  - The branch migrate applied exactly 2 (135, 136), with 133 already current.
  - All table row counts and a hash of the users table were unchanged.
  - A rerun applied 0.
  - The constraints and cascades work.
  - Main's code still runs on the migrated database.
- **SQLite test:** used a copy of real dev data (137 tables, 15,513 rows). 135 and 136 applied, every row was identical before and after, and a rerun applied 0.
- **Collision risk:** main is at 134. If another branch takes 135 or 136 before this merge, renumber before deploy.

## Backup and rollback

- **Backup before deploy:** take a manual database backup on the host (Render dashboard) and run the `db-backup.yml` workflow. Confirm both finished.
- **Fastest rollback:** set `RMT_PHASE1=0` on the host and restart. The site then renders exactly as main does, and the new tables are ignored.
- **Code rollback:** redeploy the previous main commit. The new tables are additive, and the old code never reads them, so they can stay.
- **Optional cleanup**, only after a rollback is final:

  ```sql
  DROP TABLE trap_alerts_sent;
  DROP TABLE checklist_saves;
  DROP TABLE warning_votes;
  DELETE FROM schema_migrations WHERE version IN ('135_phase1_votes_checklist', '136_trap_alerts');
  ```

- **Trap alerts:** leave the repo variable `TRAP_ALERTS_AUTO` unset. That keeps the daily job skipped.

## Daily trap alert

- **Schedule:** two crons, 15:00 and 16:00 UTC, plus a gate step that continues only when the hour in America/Los_Angeles is 08. Checked with zoneinfo on 2026-07-15, 2026-12-15, the 2027-03-13/14 change and the 2026-10-31/11-01 change: exactly one 8 AM run per day.
- **Automatic sending is OFF:** the job is skipped unless `TRAP_ALERTS_AUTO` is `true`. Manual dispatch still works when an owner runs it.
- **No duplicates:** each (member, story) pair is claimed before the send, using the primary key, so a second run cannot claim it again. A failed send releases the claim. A real run with mail disabled sent 0 and left 0 claims behind.
- **Eligibility:** the alert goes only to members who saved that city. It goes through `rmt_notify_email_direct`, which checks mail enabled, the category mute, an active account, a verified email, opt out, and the hourly and daily caps.
- **Email:** the subject is "A new trap was reported for Prague" or "3 new traps were reported for Prague". The layout matches the site's other emails and has an unsubscribe link, which returns 200. Overflow is 0 px at 600 and at 390.
- **Zero reports:** a day with no new published reports sends nothing (tested).

## End to end QA (headless Chromium, 1440 and 390)

`release_1127\e2e.py`. All 15 steps pass:

| Flow | Result |
|---|---|
| Guest Helpful vote | PASS (counted, pressed state kept) |
| Guest report, 3 steps | PASS (lands in the editor queue; the rate limit of 4 per hour per address also triggered as designed on rerun) |
| Report page makes no badge promise | PASS |
| Guest Save opens the signup dialog with the exact copy | PASS |
| Signup lands on `/saved#checklist`, save written | PASS |
| Admin moderation queue, editor publish | PASS |
| Published report shows on the city page | PASS |
| Trap alert dry run | PASS (5 members, 5 emails planned) |
| Real run with mail disabled | PASS (sent 0) |
| Existing member: saved, profile, edit, feed, messages | PASS (all 200) |
| Account delete clears the Phase 1 rows | PASS (0 left) |
| Internal links on 6 key pages | PASS (330 checked, 0 broken) |
| Horizontal overflow, every page, both widths | 0 px |
| Status codes, main vs branch, 9 pages | identical. `/report-a-trap` is new (404 on main) |
| Console | no JavaScript errors. The only errors are images stored with the old local host 8099 in the database copy, and they appear identically on main |

## Tests

- `tests/phase1_test.php`: all PASS, including:
  - SEO preserve mode
  - the warnings dedupe
  - a zero report day
  - Founding Traveler not granted
  - every flag combination
- Full suite: 194 of 194 files pass (pre push gate).

## Risks

1. **Phase 1 is ON by default once deployed.** Set `RMT_PHASE1=0` first if the deploy should not switch it on.
2. **Migration number collision** with any other branch (see above).
3. **New spam surfaces:** guest votes and reports. Both have rate limits, a honeypot and editor moderation, and nothing is published without an editor.
4. **Trap alerts are new outbound email.** They stay OFF until the variable is set. The first live runs should be watched.
5. **No hosted preview.** QA ran on local copies, not on production data.

## A+ report card

| Area | Grade | Basis |
|---|---|---|
| Migrations | A+ | Additive only. Tested on Postgres and SQLite, idempotent, data intact. |
| Trap alert safety | A+ | One run at 8 AM Pacific across DST. Claim before send. OFF by default. Eligibility checks. |
| Founding Traveler | A+ | Never granted by a report. Tested. |
| SEO preservation | A+ | Sitemaps and robots identical. No crawlable link lost. Duplication at or below main. |
| E2E | A+ | 15 of 15 flows. 0 broken links. 0 JavaScript errors. 0 overflow. |
| Tests | A+ | 194 of 194. |
| Preview | B | No hosted preview exists; local servers and screenshots only. |

## Needs owner approval

- Merge and deploy.
- Running migrations 135 and 136 on production.
- Setting `TRAP_ALERTS_AUTO=true`.
- Either SEO flag.
