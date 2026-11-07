# Indexing and SEO recommendations, with evidence (2026-11-07)

**Nothing in this file has been changed on the site.** Each item needs Nima's decision. Exact URL lists
are in `docs/research_20261107/` (`unsubmitted_all.csv`, `review_overlap_195.csv`,
`places_thin_candidates.csv`, `in_country_42.csv`). The crawl was read-only, GET only, against
https://ruinmytrip.com on 2026-11-07.

## 0. Highest value, lowest risk: the best-ranking page is not in the sitemap

`/blog/san-francisco-hotel-tax-2026`, which the repo's own Search Console reads call the best ranker
(58 impressions in 90 days, average position 11.6), is **not in sitemap-blog.xml**. Neither are
`/blog/porto-tourist-tax-2026` or `/blog/tourist-taxes-2026` (12 impressions each, Sep–Oct). The
article filter (`rmt_sitemap_articles()` in app/sitemap.php) holds them back. **Recommendation:**
submit these three, after confirming with that function why each is excluded. All three are in the
running snippet test, so add them after the 2026-11-25 read to keep the test clean.

## 1. The 195 editorial review pages: do NOT noindex them

The provisional plan was to noindex `/review/{id}/…` pages as duplicates of their parent place or city
page. **The evidence says they are not duplicates.**

| Measure (all 195) | Result |
|---|---|
| Author | RuinMyTrip Editorial (all) |
| Parent | 110 place pages, 85 city pages |
| Share of the review's sentences found on its parent page | median 23.7%, 90th percentile 39.1%, max 45.7% |
| Reviews whose full body appears on the parent | **0 of 195** |
| Highest overlap with *any* single page on the site | median 23.7% |
| Unique words on the review page | median 179; page total median 380, none under 150 |
| Search Console data for `/review/` URLs | none available in the repo; no page-level read was possible from here |

The parent pages show an excerpt and link to the review. The review page holds the only full,
indexable copy of the text and its aspect ratings. Noindexing it would remove that text from search
and keep the excerpts.

**Recommendation:** keep all 195 indexable. Fix their titles instead: 151 of 195 are cut mid-phrase
with a literal "…" (for example "Kyoto in 2026: World-Class Heritage, Priced and Policed…"), and their
slugs are cut mid-word. Fix in one batch with a control group, as the September title test did. The
fix is a short hand-written search title per review, not more truncation code. Do not add them to the
sitemap until the titles are fixed.

**One exception:** `/review/180` is about a restaurant that has moved ("Ondine has left George IV
Bridge…"). Rewrite it or noindex it; your call.

## 2. Where the real thin-content risk is

| Group | URLs | Median unique words | Under 150 words | Recommendation |
|---|---|---|---|---|
| Place pages not in any sitemap (`/p/…`, data-only) | 735 | 117 | 705 | **Decide with Search Console first.** This is the footprint the September spam update targets: hundreds of templated pages built from map data. Proposed rule: noindex a place page that has no traveler review, no editorial text, no photo **and** zero impressions in the last 16 months. Run `scripts/gsc_report.py` (page level, 16 months) against `places_thin_candidates.csv` to get the exact list. Pages with impressions (e.g. `/p/le-rapido-paris`, 86 impressions in the snippet window) stay indexable. |
| `/in/{country}` | 42 | 47.5 | 41 | Noindex (thin hubs; the 14 that mattered already 301 to `/travel-buddies/{country}`), or 301 the rest the same way. Exact list: `in_country_42.csv`. |
| `/d/{city}/places` lists | 34 | 77 | 23 | Leave indexable, keep unsubmitted (they mostly list the same places as the city page). |
| `/d/{city}?cat=` and category pages | 39 | 530 | 1 | Leave as is; real lists. |
| Neighbourhood pages | 8 | 85 | 8 | Noindex until each has reviews (backlog P2 item 3 is to build them out). |
| Tag pages | 5 | 50 | 5 | Already gated at 3+ items for the sitemap; noindex below that. |
| Blog posts not in the sitemap | 40 | 370 | 0 | Submit the ones with sources and a check date; they are real articles. |
| Guides not in the sitemap | 2 | 364 | 0 | Submit. |
| Hubs (`/blog`, `/guides`, `/reviews`, `/tags` …) | 10 | 177 | 5 | Leave indexable and unsubmitted. |

## 3. `/ruined`

Indexable today, not in sitemap-core. It now says honestly that the traveler section is empty and
labels the research lines as ours. **Recommendation:** add it to sitemap-core once it carries 10
traveler warnings. Until then the page is mostly our research, and submitting it would ask Google to
rank an empty community page. This is a one-line change in `app/sitemap.php` when the time comes.

## 4. The 63 guide titles cut with "…"

The cause is the content, not the code: guide headlines are 70 to 90 characters ("Planning Banff
Without Losing the Shuttle Lottery: Costs…") and `rmt_meta_title()` cuts them. **Recommendation:** add
an optional short `seo_title` (55 characters or fewer) to the guide and review data, written by hand
for the pages that have impressions first. Do it in one batch with a control group. **Cosmetic for
pages with no impressions**, so do those last or not at all.

## 5. Already fixed on the branch (no decision needed)

Trailing-slash duplicates (one 301, approved), Article JSON-LD dates and authors, the blog hero image,
and `/ruined` honesty.

## 5b. The proposed noindex list (144 URLs, none of them reviews)

`docs/research_20261107/proposed_noindex_144.csv`: 42 `/in/{country}`, 39 `/d/{city}` category
filters, 34 `/d/{city}/places` lists, 8 neighbourhood pages, 5 tag pages, 9 chain-branch place pages
(Starbucks, Pizza Hut and similar), 4 `/buddies/*` filters and 3 hubs (`/collections`, `/tags`,
`/leaderboard`). Search Console shows 4 impressions and 0 clicks across all `/in/*` pages in 90 days;
the other groups have no Search Console data in the repo.

Where we differ: the crawl agent proposes noindexing the 39 category pages; my table above says leave
them. Their 530-word median is mostly place names, so the agent's call is defensible. I would still
read Search Console for those 39 first, because category pages are the ones that can rank for
"[city] restaurants".

Of the 735 unlisted place pages, the agent proposes 698 leave as they are, 9 noindex (chains), and 28
fix-then-submit (each already carries an editorial review). That is narrower than my rule in
section 2, which needs Search Console data before any place is noindexed.

## 6. Still open from the crawl, lower value

- 24 place pages carry editorial `reviewRating` in Review markup while the page says "not from a
  personal trip". Recommendation: drop `reviewRating` from editorial Review markup (structured-data
  policy change; needs your OK).
- 29 pages carry FAQPage markup, mostly templated. Google shows FAQ rich results only for government
  and health sites now. Recommendation: remove it in one batch; low priority.
