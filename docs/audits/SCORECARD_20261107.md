# RuinMyTrip baseline scorecard

Run: Mon 2026-11-09 3:06 PM PST, read only, cache busted GETs (`?cb=<ms>`), UA "Mozilla/5.0 RTR-audit". No forms submitted, no site change. Owner rule 11/07 respected: no noindex, robots, canonical, sitemap or redirect proposals for public pages.
Method: python urllib + HTMLParser (title, meta description, canonical, h1, JSON-LD @type, internal links, img alt, form labels, viewport). Link check: 50 of 224 unique internal links found on the 4 pages, fetched.

## Per page evidence

| URL | HTTP | TTFB ms | Total ms | HTML bytes | Title (len) | Desc len | Canonical | h1 | JSON-LD types | Internal links (unique) | Img alt | Unlabeled fields | Viewport |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| https://ruinmytrip.com/ | 200 | 292 | 354 | 50,699 | "RuinMyTrip: honest travel reviews, warnings and travel buddies" (62) | 198 | self | 1 | Organization, ContactPoint, WebSite, SearchAction | 187 (126) | 9/9 | 0 of 5 | width=device-width, initial-scale=1 |
| https://ruinmytrip.com/d/amsterdam-netherlands | 200 | 275 | 423 | 91,461 | "Amsterdam scams, tourist traps and costs (2026) \| RuinMyTrip" (60) | 138 | self | 1 | TouristDestination, GeoCoordinates, BreadcrumbList | 179 (104) | 2/2 | 0 of 8 | same |
| https://ruinmytrip.com/p/7-portes-barcelona | 200 | 200 | 245 | 35,533 | "7 Portes, Barcelona 2026: prices & hours \| RuinMyTrip" (53) | 142 | self | 1 | Restaurant, PostalAddress, GeoCoordinates, OpeningHoursSpecification, Review, Rating, Organization, BreadcrumbList | 111 (57) | no images | 0 of 5 | same |
| https://ruinmytrip.com/review/193/sixteen-euros-for-ensaladilla-rusa-in-a-room-from-1836 | 200 | 187 | 229 | 26,341 | "Sixteen Euros for Ensaladilla Rusa in a Room From 1836" (54) | 136 | self | 1 | Review, Rating, Restaurant, PostalAddress, Person, BreadcrumbList | 102 (53) | no images | 0 of 4 | same |

Other observations:
- All 4 pages: meta robots "index, follow"; all JSON-LD parsed without error.
- Internal link sample: 50 of 224 unique fetched, **0 returned 4xx/5xx**.
- Review 193 JSON-LD author: `{"@type": "Person", "name": "@ruinmytrip", "url": "https://ruinmytrip.com/u/ruinmytrip"}` (the site's own house account typed as a Person).
- http to https: 308 one hop (apex). `https://www.ruinmytrip.com/` serves 200 (Apache) with canonical to the apex. Recorded only; redirects are owner gated and not proposed here.
- People numbers (tools/rmt_ops/daily_report.py, report dated 11/08, generated 11/09 7:20 AM PST): 12 genuine visitors in 7 days, 2 returning, 1 new registration, 2 genuine members, 0 have done anything, 0 reviews or trips by genuine members, 3 review forms opened, 8,712 suspected bots excluded.

## Scores (0 to 10)

| # | Dimension | Score | URL | Observed value |
|---|---|---|---|---|
| 1 | Design | 6 | https://ruinmytrip.com/p/7-portes-barcelona | Place and review pages have no images (0 img); home 9 images all with alt. No screenshot taken in this run, so not scored higher. |
| 2 | Functionality | 8 | https://ruinmytrip.com/ | All 4 pages 200; 0/50 links broken; forms render with labels. Write paths not exercised (read only). |
| 3 | Performance | 8 | https://ruinmytrip.com/d/amsterdam-netherlands | TTFB 187 to 292 ms, full HTML 229 to 423 ms; city page HTML 91 KB (largest). |
| 4 | Accessibility | 8 | https://ruinmytrip.com/d/amsterdam-netherlands | One h1 per page; img alt 11/11; 0 unlabeled visible fields of 22; zoom not blocked. No contrast or keyboard test run. |
| 5 | Content | 7 | https://ruinmytrip.com/review/193/sixteen-euros-for-ensaladilla-rusa-in-a-room-from-1836 | Specific, priced, dated content ("16.00 euros for ensaladilla rusa"); but every review sampled is by the house account, 0 by genuine members. |
| 6 | Internal links | 9 | https://ruinmytrip.com/ | 0/50 broken; 53 to 126 unique internal links per page. |
| 7 | Metadata | 7 | https://ruinmytrip.com/ | Canonicals self on all 4; home description 198 chars (cut off past ~155 to 160); others 136 to 142. |
| 8 | Schema | 7 | https://ruinmytrip.com/review/193/sixteen-euros-for-ensaladilla-rusa-in-a-room-from-1836 | Rich, valid types on all pages; review author is the site's house account typed as Person, not Organization. |
| 9 | SEO | 4 | https://ruinmytrip.com/ | 4 of 8 day first touch visitors from search (daily report); earlier read 11/04: most sitemap blog notes "Discovered, not indexed" (CURRENT_STATE). |
| 10 | Conversions | 1 | https://ruinmytrip.com/review/new | 12 genuine visitors/week, 3 review forms opened, 0 reviews and 0 trips by genuine members. |

## Top 5 defects (ranked by user and search impact)

1. **No real users doing anything**: 2 genuine members, 0 reviews, 0 trips, 12 genuine visitors in 7 days. A social review site with no member content; the community features (buddies, trip chat, hosts) have no one to show.
2. **Search reach is small**: search is the main source but only a handful of visitors; most blog notes were "Discovered, not indexed" on 11/04 (re-inspection due ~11/18). Fix through content and links, not technical changes.
3. **Review form drop off**: 3 review forms opened in 7 days, 0 reviews published. Each open is a near conversion lost.
4. **Review author schema**: house account `@ruinmytrip` marked up as a Person on review pages. Typing it as Organization (or labeling it as RuinMyTrip's editorial team on the page) is more accurate. Schema only; no robots or canonical change.
5. **Thin visuals and long home description**: place and review pages carry no photos (R2 photo storage still blocked on enabling R2); home meta description 198 characters is truncated in results.
