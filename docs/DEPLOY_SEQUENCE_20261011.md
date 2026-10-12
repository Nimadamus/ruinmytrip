# Deployment sequence for the 2026-10-11 work (nothing below is deployed)

Each step is one deploy, done alone, verified before the next. Every step needs the owner's go in
chat. SEO steps are kept at least a few days apart so each one's effect can be read on its own.

## Branches and what they contain

| Branch | Head | Contents | Visible change when merged |
|---|---|---|---|
| `fix/held-review-success-20261011` | 869d6ec | A guest review that goes live on email confirmation is counted as published; E2E J10 and analytics checks | none (analytics only) |
| `seo/title-test-revert-20261011` | fcf8956 | The 9 test cities return to the control title and description; result doc | titles and descriptions of 9 city pages |
| `feat/redesign-v2-20261011` | f8af30c | Place v2 + home, nav, explore, city, profile redesign, all behind switches, off; includes the held review fix | none until a switch is turned on |
| `seo/sitemap-places-20261011` | eab8e15 | Curated places sitemap (110 pages), off; verification; docs | none until the switch is turned on |
| `docs/launch-prep-20261011` | this branch | Docs only | none |

## Every step, the same checks

Before: `prod_check.py` snapshot (titles, descriptions, canonicals, robots, H1 of 26 URLs), DB row
counts through the app, the live commit. Deploy. After: deploy live, Render logs clean (no Fatal,
500 or new warning), memory and response times as before, the same snapshot diffed (only the
expected change), desktop and phone check of the changed pages on production.

Switches are Render environment variables; changing one is followed by a deploy of the same commit
so the instance restarts with it (the entrypoint also rebuilds the sitemaps). Undo = remove the
variable and deploy again, about 3 minutes. Code undo = `git revert` of the merge.

## The order

| # | When | Step | Kind | Expected change | Undo |
|---|---|---|---|---|---|
| 1 | first, any day | Rotate CRON_KEY (`docs/CRON_KEY_ROTATION_20261011.md`) | security, config | none visible; old key 404, new key 200 | give callers the new key |
| 2 | same day | Merge `fix/held-review-success-20261011` | code | none visible | revert |
| 3 | day 1 | Merge `seo/title-test-revert-20261011`, alone | SEO | 9 city titles and descriptions; nothing else in the snapshot | revert |
| 4 | day 2 | Merge `feat/redesign-v2-20261011` with every switch off | code | **none**: snapshot identical on all 26 URLs | revert |
| 4b | day 2 | Owner previews on production: any URL with `?design=v2` (that browser only, noindex), `?design=off` to leave | review | none for anyone else | n/a |
| 5 | day 4 | `RMT_DESIGN_V2=place` (the place template) | SEO, template | place pages in the new layout; title, description, canonical, robots and JSON-LD identical (tested) | unset |
| 6 | day 5 | `RMT_DESIGN_V2=place,explore,profile` | UI | explore and profile layouts; same links (tested); profiles mostly noindex already | back to `place` |
| 7 | day 11 | `RMT_DESIGN_V2=all` (adds nav, home, city) | SEO, internal links | new header and phone bar on every page (every current link kept); home links 6 places with full write ups, drops 12 empty travelers links; each city links its Top places (only places with a write up) | back to the step 6 value |
| 8 | day 18 or later | Rerun `scripts/seo/verify_sitemap_list.py`; merge `seo/sitemap-places-20261011`; then `RMT_SITEMAP_PLACES=1` | SEO, sitemap | `sitemap-places.xml` with up to 110 places; no other sitemap URL removed (tested) | unset |

Notes:
- Step 7 can be split if wanted: `RMT_CITY_TOP_PLACES=1` alone adds Top places to the current city
  page without any redesign.
- Search Console: nothing submitted by hand at any step. Measure weekly against the step dates:
  impressions and clicks for the 9 title pages, the place pages, the 110 listed places, and the
  coverage line for `sitemap-places.xml` once it exists.
- Google sign in check (`docs/GOOGLE_SIGNIN_EXTERNAL_CHECK_20261011.md`) needs no deploy and can
  happen any time the owner is at the browser.

## Tests behind this (local, 2026-10-11)

- Gate: 140 suites green on the redesign branch and on the sitemap branch; 138 on the fix branch.
- `tests/design_v2_test.php`: 97 checks on real pages (switches off = current site; on = same SEO
  signals; no lost city, place, profile or country links; preview noindex; cookie preview; top
  places only add indexable place links).
- `scripts/ci/guest_review_e2e.py`: 68 checks, desktop and phone, in the current design and in the
  redesign: guest writes a review, it survives registration, a lost session, an expired form token,
  a failed account step and a sign in; no duplicates; the email confirmation publishes it; analytics
  separate form opens, presses, failures and successes; bots and crawlers record nothing.
- `tests/sitemap_preserve_test.php`: switching the curated sitemap on adds 110 place URLs and
  removes none.
