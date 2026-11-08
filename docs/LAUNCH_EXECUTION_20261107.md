# Launch execution, 2026-11-07: release decision and 30-day plan

## What is live (verified 2026-11-07, Pacific)

* Production = `2f31b51` (Render deploy `live`, finished 8:26 AM PST 11/07, read from the Render API).
  `docs/RELEASE_CANDIDATE_20261107.md` said "Not deployed"; that line went stale when the candidate
  and later commits were deployed the same morning. The release candidate is live.
* Read-only live check (`scripts/ci/live_verify.js`, phone and desktop, no forms): **191/191** at
  about 5 PM PST 11/07 (the 140/140 in commit 08b0b78 was an earlier, shorter run of the same script).
* Indexing: robots.txt live == repo; all 144 URLs of the rejected noindex proposal serve no noindex.

## The four red suites (gate 4 of 175 red, now 176/176 green)

| Suite | Cause | Fix |
|---|---|---|
| trailing_slash_test | Real test bug: `proc_open` on `/dev/null`, which Windows lacks, so the server never started (status 0) | `NUL` on Windows (901e751, on main) |
| design_v2_test | Local dev database 13 migrations behind (`no such table: host_profiles`) | `php database/migrate.php` on the local dev copy; no code change |
| place_v2_test, sitemap_articles_test | Fresh worktree had an empty dev database | Use the real dev database; no code change |

No check was skipped or bypassed; every push went through the pre-push gate.

## Staged, not deployed: branch `rmt-launch-1107` (1414e23)

Place and review pages in the 16 cities with a sourced "Scams and traps" section now link to it
("6 scams to know in Prague"), only where the section exists. Clicks count in `warn_clicks` as
`place_scams` / `review_scams`. Links only: no robots, canonical, sitemap or redirect change.
Test: `tests/scams_link_test.php` 9/9 (also asserts no noindex on both page types). Gate 176/176.

**Release decision:** GO for `rmt-launch-1107` on Nima's approval. Deploy with the normal path
(fast-forward main, Render deploy, `live_verify.js`). Rollback: redeploy `901e751`.

## Baseline (daily report 11/06, people only)

15 genuine visitors in 7 days (7 from search), 0 registrations, 1 genuine member, 0 member reviews.
The site works; nobody knows it exists. Every target below is measured by `/cron/kpi`,
`rmt_kpi/DAILY_REPORT.md` and Search Console (`scripts/gsc_report.py`), never estimated.

## 30-day targets (to 2026-12-07)

| Measure | Now | Day 30 |
|---|---|---|
| Genuine visitors / week | 15 | 150 |
| Search visitors / week | 7 | 100 |
| Search Console clicks / 28 days | read on day 1 | 3x day-1 read |
| Genuine registrations (30 days) | 0 | 15 |
| Reviews by genuine members | 0 | 10 |
| warn_clicks / week | new | 50 |

## 30-day execution (no noindex, robots, canonical, sitemap or redirect changes)

1. **Week 1:** deploy `rmt-launch-1107` once approved. Read Search Console per page (16 months) to
   find the 20 pages already earning impressions; those get attention first.
2. **Weeks 1 to 3: content where search demand is.** Extend sourced "Scams and traps" from 16 to 30
   cities (official sources only, same tests). Improve the 20 pages with impressions: answer the
   query the page ranks for in the first screen, add real ticket and fee facts with sources.
3. **Weeks 1 to 4: internal links.** Every place, review and blog page links to its city, the
   city's scams section and the tourist tax calculator where relevant; city pages link their best
   places and reviews. Measured as crawl depth and per-page impressions.
4. **Conversion:** the review form already works without an account; watch review_form opens to
   published reviews weekly and fix the biggest drop-off step each week.
5. **Week 2 and 4: read and decide.** Snippet title test reads on 2026-11-25. Keep what moved
   clicks, revert what did not.
6. **Acquisition outside Google**, only on channels Nima approves and only within their rules
   (the answer bank of ten disclosed answers is ready). No fake reviews, profiles or testimonials.
