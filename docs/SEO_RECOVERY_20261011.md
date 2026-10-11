# SEO recovery prep, 2026-10-11

Branch `seo/recovery-prep-20261011`. Nothing here is live. Every change is behind a switch or in its
own commit so each can be deployed, measured and undone on its own. Full diagnosis:
docs/AUDIT_20261011.md section 1.

## 1. What the evidence says

* The drop is site wide, 9/28 to 9/30, and lines up with Google's September 2026 spam update
  (9/24 09:15 PDT to 10/8). City pages stayed in the sitemap throughout and fell 74% in the same week.
* Pages were not removed: the sampled place pages that lost impressions are all "Submitted and
  indexed", last crawled 9/13, robots allowed, Google canonical equals ours. No manual action.
  Page indexing: 1.14K indexed.
* So the sitemap change of 9/21 was not the main cause, and restoring it alone will not restore
  traffic. What it can do is give Google a short, strong list of place pages to recrawl.

## 2. The title test: end it

| Window | Test, 9 pages (impressions, per day) | Control (impressions, per day) |
|---|---|---|
| 08-18 to 09-14, before | 65, 2.32 | 18, 0.64 |
| 09-15 to 09-27, running, before the drop | 7, 0.54 | 14, 1.08 |
| 09-28 to 10-09, spam update | 2, 0.17 | 6, 0.50 |

The test lost 77% while the control rose, before the site wide drop. Clicks were 0 on both sides.
The nine were picked for having the most impressions, so part of the fall is regression to the
mean, but the rule written on 9/15 calls this pattern a Loss and says revert. Commit `e6b8251`
empties `RMT_DEST_SOCIAL_TITLE_TEST` (variants kept as `..._ENDED`). Lisbon now runs the same
near window lookup every other city runs, so the city page query budget goes from 60 to 61.

## 3. The place pages, all 1,350 checked live (10/11, `scripts/seo/place_candidates.py`)

| Check | Result |
|---|---|
| Answer 200 | 1,350 of 1,350 |
| Self canonical | 1,350 of 1,350 |
| robots index, follow | 830; noindex 520 (our own thin page rule: a name and a map point) |
| Signals per page (address, phone, website, hours, guide, editorial review, traveler reviews, photos) | 0: 520 · 1: 230 · 2: 178 · 3: 227 · 4: 111 · 5: 31 · 6: 53 |
| Earned impressions since June | 345 |
| Linked from the crawlable city place list (`/d/{city}/places`) | 331 |
| No internal link at all | 2 |

Internal linking finding: each city's place list shows 24 places, and the full list is only
reachable through `?all=1`, which robots.txt blocks (`Disallow: /d/*?`). So about three quarters of
places are reachable only through Nearby and Similar links on other place pages. Not changed here
(that would be a structural change); recorded for a decision.

## 4. The shortlist: 241 places (`database/sitemap_places.txt`)

Rule: answers 200, index, self canonical, three or more real signals, and either earned impressions
since June or carries a guide or a review. Ranked by impressions, then signals, then inbound links.

* 241 places; 212 of them earned impressions (1,514 of the 2,130 place impressions since June, 71%).
* 110 carry a RuinMyTrip guide and an editorial review (labelled, never counted as community).
* 143 have opening hours. Median inbound internal links: 8.
* Biggest cities: New York 35, Paris 32, Amsterdam 20, Lisbon 20, London 18, Miami 16, Rome 15.
* Every row with its signals, impressions and links: `docs/seo/place_candidates_20261011.csv`.

How it ships: `RMT_SITEMAP_PLACES=1` adds `sitemap-places.xml` to the index with only the listed
slugs that `rmt_indexable()` still accepts at generation time. Its own file, so Search Console
reports its coverage separately. Removing it is unsetting one variable.

## 5. Order and measurement

1. Deploy place page v2 first and leave it a week (RMT_PLACE_V2=1). The list goes to Google only
   after the pages it names are the better pages.
2. End the title test (can go with step 1, it is independent).
3. Then set RMT_SITEMAP_PLACES=1 and submit the sitemap index once in Search Console.
4. Read weekly for 4 to 8 weeks: impressions and clicks for the 241 against the other place pages
   (same split the title test used), recrawl dates from URL Inspection on a fixed sample of 30,
   and the separate coverage line for sitemap-places.xml.
5. Undo signal: if the 241 fall relative to the rest for two straight weeks, unset the variable.

Not done, on purpose: no blog URL merges, no URL changes, no robots.txt change, no new pages, no
mass indexing. The 520 noindexed thin places stay noindexed.
