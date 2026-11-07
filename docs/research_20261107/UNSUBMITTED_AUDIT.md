# Indexable but unsubmitted URLs on ruinmytrip.com

Date: 2026-11-07. Read-only. GET only, at most 2 requests a second, desktop Chrome UA. Nothing in the repo or on the site was changed.

Files, all in `/home/user/ruinmytrip-research/`:
- `unsubmitted_urls.csv`: one row per URL, 1,125 rows.
- `unsub/review_overlap.csv`: one row per `/review/` page, with the duplication detail.
- `unsub/state.json` and `unsub/html/`: the raw crawl.
- Scripts: `unsub_crawl.py`, `unsub_parents.py`, `unsub_analyze.py`, `unsub_recs.py`.

## Method
- **Seeds.** Every internal link on the 409 sitemap pages (from `pages.json`) that is not in a sitemap: 974 URLs, the same set as `notsm.json`. I added `/reviews`, `/blog`, `/guides`, `/tags`, `/collections` and `/communities`.
- **Crawl.** Breadth-first over unsubmitted, robots-allowed links from every indexable page. 2,023 URLs were fetched; the queue emptied.
- **Inclusion rule for the CSV.** A URL counts as indexable-but-unsubmitted only if it returns 200, has no `noindex` in meta robots or `X-Robots-Tag`, has a self canonical, and is in none of the 6 live sitemaps.
- **Excluded from the CSV:** 556 `/p/`, 56 neighbourhood and 209 other pages already `noindex` (`/hosts-locals/destinations/*`, `/d/*/travelers`…), and 75 parameter URLs that canonicalize elsewhere.
- **`unique_words`.** Main-content words in sentences found on fewer than 3 crawled pages. The corpus is the sitemap pages plus every page crawled here. On listing pages this count is mostly place names, not prose.
- **`inbound_internal_links`.** The number of distinct crawled pages (sitemap plus discovered) that link to the URL. Hubs show about 2,429 because they are linked sitewide.

## Counts per group

| Group | Indexable and unsubmitted | Median unique words | Recommendation |
|---|---|---|---|
| place_unlisted (`/p/*` not on the allowlist) | 735 | 117 | 698 leave as is, 28 fix-then-submit, 9 noindex |
| review_editorial (`/review/*`) | 195 | 380 | Leave indexable and fix titles/slugs (194). Owner decision on 1. **Not** noindex as things stand. |
| review_member | 0 | | No member reviews exist |
| in_country (`/in/*`) | 42 | 48 | noindex |
| blog_unsubmitted | 40 | 371 | fix-then-submit |
| d_category (`/d/*/restaurants`, `/hotels`, `/things-to-do`, `/experiences`) | 39 | 530 (place names) | noindex |
| d_places (`/d/*/places`) | 34 | 77 | noindex |
| hub | 10 | 177 | `/blog` and `/guides`: submit. `/collections`, `/tags`, `/leaderboard`: noindex. The rest: leave. |
| d_neighborhood | 8 | 85 | noindex. 56 siblings are already noindex. |
| tag | 5 | 50 | noindex |
| guide_unsubmitted (`/g/buzios…`, `/g/ubud…`) | 2 | 364 | fix-then-submit |
| other (5 `/buddies/*`, 10 `/post/*`) | 15 | 88 | 4 buddies noindex, the rest leave |
| **Total** | **1,125** | | |

TECH_SEO.md's 734-target sample counted about 372; the full crawl finds 1,125, mostly extra unlisted `/p/` pages linked from place pages.

## Reviews: the duplication evidence

For all 195 `/review/{id}` pages, the H1, body, "What was great" and "What nearly ruined the trip" were split into sentences and each sentence searched verbatim (whitespace, quotes and dashes normalised) in the full text of the parent, fetched today: the `/p/` page named in "Review of …", else the city `/d/` page.

- **Author.** All 195 are by "RuinMyTrip Editorial", with the ed-panel disclosure. There are 0 member reviews.
- **Parents.** 110 reviews have a place parent and 85 have a city parent. 167 parents are in a sitemap. The other 28 are unlisted `/p/` pages that are indexable.
- **`overlap_pct`** is the share of the review's words that sit in sentences found verbatim on the parent:
  - Median 23.7%. Quartiles 9.7 / 23.7 / 32.4. Minimum 3.7%, maximum 45.7%.
  - Distribution: 0–9%: 51 reviews. 10–19%: 32. 20–29%: 48. 30–39%: 50. 40–49%: 14.
  - Place parents: median 31.3%, maximum 45.7% (`/review/176` against `/p/grand-central-oyster-bar-new-york-city`).
  - City parents: median 9.2%, maximum 32.0%.
- **By section (median share on parent):** title 100% (the parent links it by title), body 30%, what was great 0%, what nearly ruins it 0%.
- **Full duplicates: 0 of 195.** The full body, the full "what was great" and the full "what nearly ruins it" are never on the parent. Parents show the opening of the body (found on 194 of 195), cut by the code to 420 characters plus an ellipsis: `views/place_show_v2.php:259` and `views/destination.php:229` (which also cuts `what_ruined` to 180). The exception is `/review/180`, whose Edinburgh parent shows no body at all.
- **Site-wide check.** A median 29.5% of a review's words appear anywhere else on the crawled site; no single page covers more than 45.7%. The median review has 179 words found on no other page (59 to 1,233; review length 165 to 1,346, median 262). **Each review page is the only place its full text appears**: noindexing all 195 would remove the only indexable copy of 78,554 words (sum of `unique_review_words`).
- **Other content not on the parent:** 2 aspect ratings (Value for money, Safety) on 185 reviews. None has photos, comments, tags or a sources list. The extra value is the full text, not media.
- **Exception to flag.** `/review/180/ondine-has-left-george-iv-bridge-what-we-wrote-while-it-was-open`: its own title says the restaurant has moved. Its overlap with the parent is 8%. Rewrite it or noindex it. This is an owner decision.
- **Title quality.** 151 of 195 titles are cut with "…", and the slugs are cut mid-word. This is the fixable problem in this group.

Reading of the evidence. The provisional plan treats the review pages as duplicates of their parents, so they could be noindexed. The data does not support that. The parent pages are the excerpts, and the review pages hold the full text. There are two coherent options:

- **(a) Leave them indexable but unsubmitted** (recommended for now). Fix the truncated titles and slugs. Revisit once GSC shows whether Google indexes them.
- **(b) Noindex them, but first make the parent carry the full text.** Render the full body plus the great and ruin text on the `/p/` or `/d/` page, then noindex `/review/*` or canonicalize it to the parent.

Counter-risk: 195 desk-research editorials with star ratings under a review URL by the house account is a scaled-content footprint, which argues for (b) long term. That is a judgement call the data does not settle.

## Search value (GSC)

Repo files only.
- **`/review/*`:** no GSC data in the repo for this group. `docs/sitemap_audit_20261102.csv` covers `/p/` only, and the `seo_*.json` files cover `/d/`, `/p/` and `/blog/` only.
- **`/in/*`:** across all 56 pages, 4 impressions and 0 clicks in 90 days (`docs/Q7_IN_COUNTRY_MIGRATION_MAP.md`, 2026-10-01). Of those, `/in/cuba` had 3 and `/in/japan` had 1.
- **place_unlisted:** 100 of the 735 pages are in `sitemap_audit_20261102.csv`. Over 16 months they had 157 impressions and 0 clicks; the top one is `pizzeria-ai-marmi-rome` with 23. 78 are "Submitted and indexed" from the old sitemap, and 22 are unknown to Google. There is no GSC data for the other 635.
- **blog_unsubmitted.** `/blog/san-francisco-hotel-tax-2026` is **not in sitemap-blog.xml**, yet the repo calls it the best-ranking page on the site: 58 impressions in 90 days at position 11.6 (`docs/ACQUISITION_ORGANIC.md`), and 27 impressions in the 2026-09-19..10-16 window (`seo_snippet_baseline_20261018.json`). In that same window `/blog/porto-tourist-tax-2026` and `/blog/tourist-taxes-2026` had 12 impressions each. There is no data for the other 37.
- **d_places, d_category, d_neighborhood, tag, hub, guide_unsubmitted, other:** no GSC data in the repo for these groups.

## Recommendations and reasons

Per-URL reasons are in the CSV `reason` column. Group notes beyond the table:
- **in_country:** noindex,follow. These pages are about 48 unique words and have no search history. The Q7 plan still applies: 301 the 14 that have a matching `/travel-buddies/{country}` page. All 42 found here return 200, so none of that plan has been carried out yet.
- **d_places, d_category:** noindex,follow. They list place names with "Write a review" on every row and have no reviews, yet the title promises "honest reviews".
  - Uncertainty: "Restaurants in Paris" is a real query, and these pages have 815 to 825 words of names.
  - Alternative: leave them as they are, which is current policy per `app/sitemap.php:161`.
- **d_neighborhood:** noindex. 56 of the 64 neighbourhood pages already noindex, and these 8 are the odd ones out.
- **place_unlisted:**
  - Leave 698 as they are, which is owner policy from 2026-10-30.
  - Fix-then-submit 28: each already carries an editorial Official Review because it is a review parent. Fact-check them and add them to `database/sitemap_places.txt`.
  - Noindex 9 chain branches (`starbucks-*`, `pizza-hut-*`).
- **blog_unsubmitted:** fix-then-submit, starting with `san-francisco-hotel-tax-2026`. All 40 have 342 to 1,103 unique words. The exclusion reason comes from `rmt_sitemap_articles()` (roundup with more than 2 cities, or near-duplicate) and cannot be seen from outside. Run it to confirm before acting.
- **guide_unsubmitted:** fix-then-submit. Both titles are truncated, and both may be near-duplicates of a blog post.
- **hub:** `/collections`, `/tags`, `/leaderboard` are 39 to 42 unique words. `/reviews` submits itself once a member review exists.
- **other:** noindex `/buddies/adventure`, `/business`, `/festival`, `/resort` (under 150 unique words); leave `/buddies/trip` and the house-account questions `/post/7` to `/post/16` (noindex them if they draw no replies).

## Proposed noindex list (144 URLs)

The list is exactly the CSV filter `recommendation == "noindex"`: 42 `in_country`, 39 `d_category`, 34 `d_places`, 9 chain places, 8 `d_neighborhood`, 5 `tag`, 4 buddies, 3 hubs.

Reproduce it with:

```
python3 -c "import csv;[print(r['url']) for r in csv.DictReader(open('/home/user/ruinmytrip-research/unsubmitted_urls.csv')) if r['recommendation']=='noindex']"
```

Pending owner decision: `/review/180/…`. **No `/review/` URL is on the noindex list.**

## Uncertainty
- `overlap_pct` counts exact sentences only. A paraphrase on the parent counts as unique, so the true semantic overlap is somewhat higher.
- Sentence splitting is regex-based.
- Crawl coverage is the pages reachable by links from sitemap pages and the seeded hubs. Orphan URLs, such as unlinked `/review/` IDs, would be missed. The `/review/` IDs seen run from 8 to 208.
- The exclusion reasons for blog posts and guides were inferred, not read from the database.
