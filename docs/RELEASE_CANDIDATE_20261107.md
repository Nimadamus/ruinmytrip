# Release candidate 2026-11-07: go/no-go

**Candidate:** branch `claude/relaxed-wright-enrc9x`, a fast-forward of `main` (`b045de9`): 22 code
and content commits plus documentation commits. **No database migration, no new environment variable, no sitemap,
canonical or noindex change** except the approved trailing-slash 301. `main` has not moved since
the branch was cut. **Not deployed.**

## Recommendation: GO, on your approval

Everything code can verify is green on the production image against both databases. The
candidate makes the live site strictly safer (account suspension, report alerts, hosting paused)
and more accurate (the Barcelona calculator overcharged; three posts had factual errors). What
remains open is legal review and real members. Neither is fixed by waiting to deploy.

## What ships

| Area | Change |
|---|---|
| Homepage | New hero: "Find out what could ruin your trip, before it does." Three first actions: warn the next traveler (one sentence, typed right there), explore a destination (with scam warnings), find travel companions. Travel map moved to its own band. Brand-first title and Organization schema |
| Trust | `/ruined` separates traveler warnings from researched ones (it called them all "from people who were there"); legal pages show their real last-updated month |
| Content | "Scams and traps" from official travel advice on 8 city pages (42 sourced items); "Trip went wrong? What you can claim" (EU261, UK, Canada, US refunds, baggage, package travel, card disputes); 5 tourist-tax posts corrected |
| Calculator | Barcelona now taxes at most 7 nights per person: 10 nights for two in a four star was €168, is €117.60. Exemption notes for Barcelona, Rome, Nice |
| Community | A helpful vote now notifies the writer; signed-out vote buttons lead to sign in |
| Safety | Moderators can suspend or reinstate an account from the queue (before: only by a database script); staff are emailed on each report (six an hour at most); new stays and host applications are paused, and existing stays keep working |
| Quality | Trailing-slash URLs 301 once to the canonical path; Article JSON-LD dates and authors; blog hero images sized for phones; 44px tap targets; one `<main>` per page; 30 pages clean on axe (serious/critical, WCAG 2 A/AA) |

## Evidence

| Check | Result |
|---|---|
| Repo gate (`scripts/gate.sh`) | 171/173 suites. The 2 red suites fail identically on `main`. Their cause is local fixture data: `sitemap_articles_test` passes once local timestamps match production, and `place_v2_test` needs a Lisbon café that exists only in production data |
| Browser journeys, 7 journeys × phone and desktop (`scripts/ci/launch_journeys_e2e.js`) | **153/153** on the production Docker image (Apache) with SQLite; **153/153** on the same image against a fresh Postgres 16 migrated by the entrypoint (migrations 119, latest `120_host_unavailable`) |
| Trailing slash (`tests/trailing_slash_test.php`) | 23/23 on Apache and on `php -S`; all 405 sitemap URLs 200 untouched, slash twins one 301; no loops on `/assets/`, `/uploads/`; open-redirect attempts stay on site; 400 repeats, 0 non-301 |
| Smoke (`scripts/smoke_test.sh`) | All checks pass on the RC image. Against live production, read-only, all pass except the new trailing-slash check (expected until deploy) |
| New SQL on Postgres | helpful notifications and rollup, suspend/reinstate, split warning counts, staff alerts and the stays pause all exercised against Postgres 16 |
| Accessibility | axe-core 4.10.2, serious/critical WCAG 2 A/AA: 30 key pages clean on phone and desktop |
| Facts | 3 scam quotes, EU/UK/Canada amounts, 1,519 SDR, and the Barcelona/Lisbon/Nice/Rome exemption quotes re-read by hand on the official pages |

## Deploy (only on your go)

```bash
git push origin claude/relaxed-wright-enrc9x:main
```
Render deploys `main` automatically. The entrypoint publishes the editorial data, which adds the claims
page and the corrected tax posts. Then:
1. `curl https://ruinmytrip.com/readyz` shows `migrations=119 latest=120_host_unavailable` (unchanged).
2. `scripts/smoke_test.sh https://ruinmytrip.com` passes, including `/d/kyoto-japan/` → 301.
3. Open the homepage, `/ruined`, `/d/rome-italy#scams`, `/blog/trip-went-wrong-what-you-can-claim` and
   `/tourist-tax-calculator?city=barcelona&tier=4&nights=10&people=2` (expect €117.60).
Do not run the browser journeys against production: they create accounts.

**Rollback:** `git revert --no-edit b045de9..<deployed head>` then push to `main`. No migration to undo.
Hosting can be reopened independently later by setting `rmt_hosting_paused()` to false.

## Honest regrade

| Area | Before | Now | Evidence or what still blocks |
|---|---|---|---|
| Engineering and reliability | A- | **A** | Journeys green on both databases on the production image |
| Safety and moderation | A- | **A** | Suspension from the queue, report alerts, stays paused. Moderator response time is not committed (yours) |
| Legal readiness | D | **C** | Full draft in docs/legal/TERMS_DRAFT_20261107.md; not reviewed or published |
| Homepage and design | B+ | **A-** | Purpose in one line, three actions, axe-clean |
| Mobile | A- | **A** | No overflow anywhere tested, 44px targets |
| Navigation and discovery | B- | **B+** | Claims and warnings in the footer; blog and guides still not in the main nav (an earlier decision of yours) |
| Content quality and trust | B | **A-** | Honest `/ruined`, sourced scams and claims, tax errors fixed. 133 templated budget posts remain |
| Technical SEO | B- | **B+** | Slash duplicates fixed, structured data fixed. Indexing decisions pending (docs/SEO_RECOMMENDATIONS_20261107.md) |
| Accessibility | n/a | **A** | 30 pages clean |
| Performance | A- | **A-** | Unchanged except blog heroes |
| Community features | n/a | **A-** | Everything works end to end; nobody uses it yet |
| Community activity | D | **D** | Needs launch and outreach, not code (docs/FIRST_100_USERS_20261107.md) |
| Organic traffic | F | **F** | Needs time and the indexing decisions |
| **Launch readiness overall** | **C+** | **B+** | **A- for everything code controls.** The gap to A- overall is the Terms (counsel) and the first real members |

## The decision needed to go live

**Approve deploying this release candidate to production** (the push above). Recommended alongside it,
but not required for the deploy:
1. Send the Terms draft to counsel. Hosting stays paused until it is published.
2. Delete outreach post 7.2 (hosting recruitment, scheduled from 9 November) wherever it is queued.
3. Send the 20 friend messages in docs/FIRST_100_USERS_20261107.md the day the deploy is live.
