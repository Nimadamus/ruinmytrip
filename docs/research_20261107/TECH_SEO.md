# ruinmytrip.com technical SEO and performance audit

Date: 2026-11-07. Read-only, GET only, at most 2 requests a second, desktop Chrome UA. Every sitemap URL was crawled with redirects off. 734 internal link targets were checked, sampled from 32 representative pages. Playwright ran on 6 templates at 2 viewports.

Raw data, all in `/home/user/ruinmytrip-research/`:
- `crawl.csv`: one row per sitemap URL.
- `linkcheck.csv`: the internal link targets.
- `perf.json`: the Playwright results.
- `shots/`: 12 screenshots.

## Grades

| Area | Grade | Main thing standing between it and an A+ |
|---|---|---|
| Indexing / crawl | **B** | Trailing-slash duplicates with self-canonicals. About 370 indexable URLs are linked but not submitted. |
| Metadata | **B-** | 85 destinations share one boilerplate title. Literal "…" truncation in 63 guide titles. 217 titles over 60 characters. |
| Structured data | **B** | Invalid or thin Article markup on guides. Editorial star ratings that were not written from a visit. FAQPage on templated pages. |
| Internal linking | **C+** | /blog and /guides are missing from home, nav and footer. Articles and places have about 2 inbound links each. |
| Performance | **A-** | One full-size hero image. Images have no width/height. HTML is `no-store`. Cloudflare does not cache anything. |
| Mobile | **A-** | No overflow and a clean layout. On blog posts the article starts below the fold. |

## Ranked findings

### 1. Trailing-slash URLs return 200 and self-canonicalize to the slash URL (High)
`/d/paris-france/`, `/blog/venice-access-fee-2026/`, `/g/kyoto-japan-travel-guide/` and `/about/` all return 200. Each one sets `<link rel="canonical">` to the **slash** version, which makes every page in those groups a crawlable duplicate.

`/p/*`, `/buddies/` and `/travel-buddies/*/` are fine because their controllers set an explicit canonical.

Cause:
- `public/index.php:7` trims the slash for routing.
- `rmt_current_url()` in `app/seo.php:4-6` builds the canonical from the raw `REQUEST_URI`.

Fix: 301 any non-root path ending in `/` to the version without it, or rtrim the path in `rmt_current_url()`.

### 2. About 372 indexable, self-canonical URLs are linked internally but not in any sitemap (High)
This came from the 734-target sample.

| Path | Count | Notes |
|---|---|---|
| `/review/{id}/…` | 195 | Editorial reviews by the house account. The homepage links 33 of them. |
| `/in/{country}` | 42 | |
| `/d/{city}/places` and `/d/{city}/n/{hood}` | 58 | |
| `/p/*` | 23 | Not on `database/sitemap_places.txt` |
| `/post/{id}` | 10 | |
| `/blog/*` | 9 | e.g. `alcatraz-tickets-2026`, `dubai-tourism-dirham-2026` |
| `/g/*` | 2 | `buzios`, `ubud` |
| `/tag/*` | 5 | |
| Hubs | | `/blog`, `/guides`, `/reviews`, `/leaderboard`, `/collections`, `/communities`, `/meetups`, `/going`, `/ruined`, `/tags` |

`app/sitemap.php` holds these back on purpose: editorial reviews, non-allowlisted places, neighborhoods, categories, and articles that fail `rmt_sitemap_articles()`. They still serve `index, follow`, though.

That sends a mixed signal. "Not good enough to submit" should mean noindex, or else submit them. As it stands, Google finds them through links and judges them without the curation the sitemap was meant to apply.

The `/review/*` pages are the largest group. Their titles are truncated, 151 of 195 end in "…". Their slugs are cut mid-word (`…like-never-befor`, `…the-view-is-free-`). About 30% of review/100's text also appears on `/p/tate-modern-london`.

### 3. Blog and guides are near-orphans in the link graph (High)
Neither `/blog` nor `/guides` is linked from the homepage, the global nav or the footer. Each hub is linked only from its own articles' breadcrumbs.

Inbound links from the 409 sitemap pages, median per page:
- Blog posts: 2. 32 posts have just 1.
- Guides: 3.
- Places: 2. 23 places have 1.
- Destinations: 10.

The homepage links 53 of the 409 sitemap URLs. It links no blog posts or guides, but it does link 33 unsubmitted `/review/` pages.

Fix:
- Add Blog and Guides to the footer or nav.
- Add a "latest notes" or "guides" module on home.
- Cross-link fee notes ↔ the matching `/p/` and `/d/` pages. For example, `blog/eiffel-tower-tickets-2026` should be linked from the Eiffel Tower place page and from Paris.

### 4. Scaled-looking title templates (Medium)
- **All 85** destination titles are `{City} 2026: costs, tickets, taxes and what nearly ruins it | RuinMyTrip`. Their meta descriptions are unique and hand-written, so only the title reads as a template.
- Places use 4 variants of `{Place}, {City} 2026: prices & hours`. 37 of them drop the brand.
- Guide titles are cut with a literal "…" inside `<title>` on 63 of 82. Examples:
  - `Hoi An Practical Guide: Tickets, Da Nang Transfers, Flood…`
  - `Buzios Travel Guide 2026: Getting There From Rio, Which…`

  The truncation comes from `app/seo.php:455`. Let Google truncate on its own, or write shorter titles.
- 217 of 409 titles run over 60 characters, and 149 descriptions over 160. Seven destination descriptions run 267 to 401 characters, for example `/d/medellin-colombia` at 401.
- There are no exact duplicate titles or descriptions among sitemap URLs. Two H1s repeat:
  - `/buddies`, `/buddies/backpacking` and `/buddies/road-trip` share one H1.
  - `/` and `/contribute` share one H1.

### 5. Structured data problems (Medium)
- **Guides `Article`** (`app/controllers.php:1751`):
  - `datePublished` is the raw MySQL value `"2026-07-25 03:30:37"`. That is not ISO 8601: it has no `T` and no timezone.
  - The markup has no `author`, `image` or `dateModified`.
- **Blog `Article`** has an Organization author and an ISO date. It has no `image`, even though every post has a hero image, and no `dateModified`.
- **Place `Review`** appears on 24 restaurant and hotel pages. It is authored by "RuinMyTrip Editorial" with a 1–5 `reviewRating`.
  - It is not self-serving in Google's sense, because the site does not own the venues.
  - But the page itself says the review was "written … from published research and official sources, not from a personal trip". A star rating with no first-hand experience behind it is the kind of review markup Google's reviews-system and snippet policies discount, and it could be treated as spammy.
  - Recommendation: keep the text, drop the `reviewRating`, or drop the `Review` node entirely until real traveler reviews exist.
  - `aggregateRating` is correctly withheld at zero reviews.
- **FAQPage** is on 29 core pages: 15 `/travel-buddies/*`, 10 `/cruise-buddies/*`, `/map`, `/tourist-tax-calculator` and 2 `/e/*`.
  - The answers are visible on the page, which is good.
  - Google has shown FAQ rich results only for authoritative government and health sites since 2023, so this markup gains nothing.
  - The country pages use templated Q&A ("Is {Country} good for solo travelers?"), which adds to the scaled-content footprint. Consider removing it.
- **Event** on `/e/web-summit-lisbon` has `location.name: "Lisbon"` and no venue or street address, although the page text names MEO Arena. It has no `image` and no `organizer`. Google's Event markup requires a real venue address to be eligible.
- **No problems found:**
  - All JSON-LD on all 409 URLs parses.
  - `TouristDestination` has geo but no rating.
  - `BreadcrumbList` is everywhere.
  - `WebSite` has a `SearchAction`.
  - `ProfilePage` is on `/u/ruinmytrip`.
  - No `AggregateRating` appears anywhere it is not backed by data.

### 6. Robots-blocked parameter URLs are linked in bulk (Low–Medium)
City pages link `?ask=avoid`, `?ask=ruined`, `?ask=skip`, `?ask=stay` and `?ask=tip`. `/d/{city}/places` links `?cat=*` and `?type=*`.

`robots.txt` disallows `/d/*?`, so Google cannot see these URLs' canonicals. They will pile up as "Indexed, though blocked by robots.txt" or as wasted discovery.

Other parameter URLs are crawlable and canonicalize correctly but are still linked:
- `/explore?category=*`
- `/map?c=*`, titled "My travel map: 1 country", which is thin
- `/talk?sort=top`

Prefer JS or buttons for the `?ask=` prompts, or `rel="nofollow"` on them.

### 7. Sitemap hygiene (Low)
- `core`, `destinations` and `profiles` have no `<lastmod>`. Blog, guides and places do have it.
- The `profiles` sitemap contains only `/u/ruinmytrip`. That page is 228 KB, 12.5k words and 332 links. Every other `/u/*` is noindex. Including the house account on its own adds little.
- Every sitemap URL returned 200. There were no redirects, no 4xx/5xx, no noindex, no `X-Robots-Tag` and no non-self canonicals in the sitemaps.

### 8. Thin pages in the sitemap (Low)
"Unique text" here means words in sentences that appear on fewer than 3 pages, after nav, header and footer are stripped. Twelve sitemap URLs fall under 250 unique words:
- `/affiliate` (96), `/founding` (119), `/contribute` (120)
- `/buddies/road-trip` (146), `/hosts-locals/become-a-host` (154), `/buddies/backpacking` (175)
- `/contact`, `/start`, `/safety`, `/terms`, `/hosts-locals/destinations`
- `/d/tehran-iran` (215)

Legal and utility pages are fine. The weak ones to fix are `/buddies/backpacking`, `/buddies/road-trip`, `/founding`, `/affiliate` and `/d/tehran-iran`: add content or drop them from the sitemap.

Median unique words by group:

| Group | Median unique words |
|---|---|
| Destinations | 680 |
| Places | 560 |
| Guides | 517 |
| Blog | 401 |

Repeated boilerplate appears on 85 to 354 pages: the rating widget, "Written by RuinMyTrip Editorial…", and "Post your dates…". That is normal UI, but on blog posts it sits **above** the article body (see item 10).

### 9. Performance (A-)
TTFB is warm in every row below. The 1,817 ms home TTFB on mobile was the first request of the run, so it may have hit a cold start. Across the crawl, median response was 0.36 s and p90 was 0.88 s. The slowest were `/d/buenos-aires-argentina` at 3.3 s and `/d/buzios-brazil` at 3.2 s.

| Page | Viewport | TTFB | FCP→LCP | Requests | Transfer | LCP element |
|---|---|---|---|---|---|---|
| home | mobile | 1,817 ms | 2.6 s | 21 | 485 KB | text |
| home | desktop | 592 ms | 1.05 s | 25 | 668 KB | text |
| /d/paris-france | mobile / desktop | 840 / 237 ms | 1.8 / 1.2 s | 21 | 512 / 695 KB | hero img |
| /p/tate-modern-london | mobile / desktop | 257 / 293 ms | 0.7 / 0.9 s | 21 | 365 KB | text |
| /blog/venice-access-fee-2026 | mobile / desktop | 360 / 332 ms | 0.9 / 1.2 s | 12 | 400 KB | hero img |
| /buddies | both | about 260 ms | 0.65 s | 11 | 183 KB | text |
| /tourist-tax-calculator | both | about 255 ms | 0.6 s | 11 | 181 KB | text / H1 |

What blocks an A+:
- **The blog hero is the full original, 218 KB.** It is served at the same size on both viewports with no `srcset`, although `/media/w/480/` exists and is 23 KB for the same file. City pages already use the `/media/w/480/` variant on mobile, so this is a small change to the blog template.
- **No `<img>` on any tested page has `width` and `height` attributes.** CLS was 0.048 on the desktop blog post and about 0 elsewhere.
- **Render-blocking CSS:** `app.css` and `design-v2.css` everywhere, plus `place-v2.css` on place pages. CSS totals about 39–46 KB. No synchronous head scripts were found.
- **Fonts:** 114 KB of woff2, Inter and Fraunces, both preloaded on every page. Subset Fraunces or cut a weight.
- **HTML caching:** HTML is `Cache-Control: no-store, no-cache`. That disables bfcache and edge caching, so every visit pays origin TTFB on the Render free instance. Long-lived static assets show `cf-cache-status: DYNAMIC`, which means Cloudflare is not caching CSS, fonts or `/media`. Add a Cloudflare cache rule for `/assets/*` and `/media/*`. Consider a short `s-maxage` for signed-out HTML; the app already has a 60 s page cache.
- **Maps:** city and place pages load Leaflet from cdnjs plus 4–6 OSM tiles eagerly. Lazy-load the map below the fold.

### 10. Mobile (A-)
- At 390 px there is no horizontal overflow on any page (`scrollWidth == innerWidth`). The viewport is correct and the bottom tab bar does not cover content.
- On `/blog/*` at 390×844, the breadcrumb, tags, H1, byline, a star-rating widget and the editorial disclaimer box fill the first screen. The article text and hero image start below the fold (`shots/blog-mobile.png`). Move the rating widget below the article.

## Other checks that passed
- `http://` and `www` both 301 to `https://ruinmytrip.com/`.
- HSTS is on (180 days).
- Brotli compression is on.
- Unknown URLs return a real 404 with noindex. A hidden place returns 410.
- Of the 734 internal link targets, 0 were broken. The only 302s were `/trip/new`, `/buddies/new` and `/report`, all redirecting to `/plan` or login.
- Every sitemap page has exactly one H1 and a meta description.
- og:title and og:image are on 409 of 409 pages. There are no hreflang tags, which is correct for an English-only site.
- `robots.txt` blocks forms and filter duplicates sensibly.

## Priority fix list
1. Trailing-slash 301, or strip the slash in `rmt_current_url()`.
2. Decide on each indexable-but-unsubmitted group (`/review/*`, `/in/*`, neighborhoods/categories, non-allowlisted `/p/*`, excluded blog posts): noindex it or submit it. Fix the truncated review slugs and titles.
3. Link `/blog` and `/guides` from the nav, footer and homepage. Cross-link fee notes to their place and city pages.
4. Write varied destination titles. Remove the "…" from `<title>`. Cap descriptions at about 160 characters.
5. Fix guide Article markup: ISO date, author, image, dateModified. Add `image` to blog Article. Drop `reviewRating` from editorial place reviews. Drop the templated FAQPage. Give Event a real venue address.
6. Use the responsive blog hero (`/media/w/…` + `srcset`). Add width/height to images. Add Cloudflare cache rules. Lazy-load the map.
