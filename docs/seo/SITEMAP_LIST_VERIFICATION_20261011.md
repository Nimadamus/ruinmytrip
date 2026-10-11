# Curated places sitemap: verification before it is switched on (2026-10-11)

Live check of every page on the proposed list, run 2026-10-11 09:02 to 09:12 PDT with
`scripts/seo/verify_sitemap_list.py` (read only, bot user agent so analytics never count it).
Per page results: `docs/seo/sitemap_list_verification_20261011.csv`. Nothing is live: the sitemap
group stays off (`RMT_SITEMAP_PLACES` unset).

## The six questions

| Question | Result | Evidence |
|---|---|---|
| Do the proposed pages have useful, original content? | **Only 110 of the 241 do.** The list is cut to those 110. | Text of each page with the shared page template removed ("own words"): the 110 with our researched write up have 321 to 606, median 408. The other 131 (address, hours, map only) have a median of 57, minimum 19. No two listed pages are near duplicates (highest 5 word shingle overlap 0.35). |
| Is every submitted URL canonical and indexable? | Yes, 241 of 241 (so all 110) | Each answers 200 with no redirect, `index, follow`, and a canonical equal to its own URL. The sitemap code also re-asks the indexing rule for every listed page at build time, so a page that later turns thin drops out on its own. |
| Do internal links point at the intended indexed pages? | Yes | 212 hub pages fetched (home, every city, its place list, its category pages): 0 links to a variant of a listed URL (no query strings, trailing slashes or case changes). Every one of the 110 has at least 2 hub links (median 2). Two pages on the old list had none (`hotel-wing-international-shinjuku-tokyo`, `mome-lisbon`); both are thin and are off the new list. |
| Does the homepage waste its strongest links on excluded pages? | Today, yes | Of 80 links on the current home page, 20 go to noindex pages (12 city "travelers" pages with nobody on them, 8 sign up and trip forms) and 0 go to any place. The redesigned home (`feat/redesign-v2-20261011`, off) drops the 12 empty travelers links, keeps every travel buddy and cruise page link, and adds 6 links to listed places with full write ups. |
| Will existing indexed pages be removed by accident? | No | `tests/sitemap_preserve_test.php` builds the sitemaps both ways the way a deploy does: off 599 URLs, on 709, added 110, removed 0, robots.txt identical. The places file is its own child sitemap; nothing else changes. |
| Does Search Console support the priorities? | Yes, with one gap | 90 days to 2026-10-09: the 110 kept pages drew 1,066 impressions, 70% of the 1,518 the whole 241 drew. 212 of the 241 had impressions. 30 indexable places off the list drew 5 or more impressions; the biggest are Nini's Coffeebar Amsterdam (61), Stade Roland Garros Paris (61), Benaki Museum Athens (37), all thin (no write up). They stay indexable and linked; they are not submitted until they have something to read. |

## What changed on the branch

- `database/sitemap_places.txt`: 241 to 110 slugs (the ones with a full write up).
- `tests/sitemap_places_test.php`: list size bound 100 to 250.
- `tests/sitemap_preserve_test.php` (new): switching on only adds listed place pages.
- `scripts/seo/verify_sitemap_list.py` (new): this check, rerunnable before switching on.

## Recommendation for the order

1. Place page template (place v2) live first.
2. Internal links next: the redesigned home and the city "Top places" block
   (`RMT_CITY_TOP_PLACES`), both pointing only at places with a full write up.
3. Curated sitemap last, at least a week later: `RMT_SITEMAP_PLACES=1` and a deploy (the entrypoint
   rebuilds the sitemaps). Rerun this check the day before; submit nothing by hand.
4. The thin places: worth more as candidates for a real write up (Le Rapido, Nini's Coffeebar,
   Stade Roland Garros already earn impressions) than as sitemap entries.

Rollback for step 3: unset `RMT_SITEMAP_PLACES` and deploy; the next build has no places file.
