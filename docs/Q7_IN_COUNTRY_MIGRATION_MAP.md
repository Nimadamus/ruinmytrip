# Q7: /in/{country} URL migration map (proposal, NOT executed)

Prepared 2026-10-01 from the full crawl (01:36 to 02:16 PDT), Search Console URL Inspection run per URL
on 2026-10-01, and the Search Analytics pages report for the last 90 days. Nothing here has been changed.

## What these pages are

`/in/{country}` lists the country's cities and links to who is going. 56 pages, all `index, follow`,
all self canonical, none in the sitemap since 2026-09-21, 71 words on average (55 of 56 under 150).
Search history across all 56 in 90 days: **4 impressions, 0 clicks** (/in/cuba 3 at position 96, /in/japan 1 at 61).
34 are indexed, 22 are unknown to Google.

Internal links into them: the country breadcrumb on every city page (`views/destination.php`, and the
BreadcrumbList JSON-LD), the country hub links, and the footer/explore lists. A redirect without changing
those links would leave thousands of internal links pointing at a 301, so the links change in the same commit.

## Recommendation

* **14 countries with a matching `/travel-buddies/{country}` page: 301 to it**, and point the breadcrumb and
  every internal link at the destination URL directly. The buddy page is the richer page (about 500 words, in the
  sitemap, and six of the fifteen already earn impressions at an average position near 10), so this consolidates two thin-vs-real pages on one
  intent instead of splitting it.
* **42 countries without one: no URL change.** Keep `/in/{country}`, enrich it (cities with photos, who is going
  across the country, the country's open questions) or leave as is. A redirect to a city or to /explore would be
  a soft 404 for a country query and would lose the page for no gain.
* Indonesia: there is `/travel-buddies/bali`, not `/travel-buddies/indonesia`. Bali is not the whole country, so
  `/in/indonesia` stays.
* Risk: minimal. 4 impressions of history in total. External backlinks were not checked (no backlink data source in this session); a 301 passes them on anyway.
  Rollback: remove the redirect rule; the old view is untouched.

## Full map

| Current URL | Canonical | Robots | Words | Google index (URL Inspection) | Search 90 d (imp, clicks, pos) | Internal pages linking | Linked from (page types) | Action | Exact 301 destination |
|---|---|---|---|---|---|---|---|---|---|
| `/in/argentina` | `/in/argentina` | index, follow | 55 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/australia` | `/in/australia` | index, follow | 56 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/austria` | `/in/austria` | index, follow | 54 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/bahamas` | `/in/bahamas` | index, follow | 60 | URL is unknown to Google | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/brazil` | `/in/brazil` | index, follow | 112 | Submitted and indexed | 0 | 10 | d, explore, in | Keep, enrich | none |
| `/in/cambodia` | `/in/cambodia` | index, follow | 59 | Submitted and indexed | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/canada` | `/in/canada` | index, follow | 82 | URL is unknown to Google | 0 | 10 | d, explore, in | Keep, enrich | none |
| `/in/china` | `/in/china` | index, follow | 58 | URL is unknown to Google | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/colombia` | `/in/colombia` | index, follow | 85 | URL is unknown to Google | 0 | 12 | d, explore, in | 301 | `/travel-buddies/colombia` |
| `/in/costa-rica` | `/in/costa-rica` | index, follow | 58 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/croatia` | `/in/croatia` | index, follow | 59 | URL is unknown to Google | 0 | 6 | d, explore, in | 301 | `/travel-buddies/croatia` |
| `/in/cuba` | `/in/cuba` | index, follow | 57 | Submitted and indexed | 3, 0, 95.7 | 7 | d, explore, in | Keep, enrich | none |
| `/in/czechia` | `/in/czechia` | index, follow | 56 | URL is unknown to Google | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/denmark` | `/in/denmark` | index, follow | 59 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/dominican-republic` | `/in/dominican-republic` | index, follow | 60 | URL is unknown to Google | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/egypt` | `/in/egypt` | index, follow | 55 | Submitted and indexed | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/france` | `/in/france` | index, follow | 85 | Submitted and indexed | 0 | 12 | d, explore, in | 301 | `/travel-buddies/france` |
| `/in/germany` | `/in/germany` | index, follow | 84 | Submitted and indexed | 0 | 9 | d, explore, in | Keep, enrich | none |
| `/in/ghana` | `/in/ghana` | index, follow | 57 | Submitted and indexed | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/greece` | `/in/greece` | index, follow | 86 | Submitted and indexed | 0 | 9 | d, explore, in | 301 | `/travel-buddies/greece` |
| `/in/hong-kong` | `/in/hong-kong` | index, follow | 61 | Submitted and indexed | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/hungary` | `/in/hungary` | index, follow | 57 | URL is unknown to Google | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/iceland` | `/in/iceland` | index, follow | 58 | Submitted and indexed | 0 | 7 | d, explore, in | 301 | `/travel-buddies/iceland` |
| `/in/india` | `/in/india` | index, follow | 58 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/indonesia` | `/in/indonesia` | index, follow | 80 | URL is unknown to Google | 0 | 11 | d, explore, in | Keep, enrich | none |
| `/in/iran` | `/in/iran` | index, follow | 52 | Submitted and indexed | 0 | 4 | d, explore, in | Keep, enrich | none |
| `/in/ireland` | `/in/ireland` | index, follow | 55 | Submitted and indexed | 0 | 7 | d, explore, in | Keep, enrich | none |
| `/in/israel` | `/in/israel` | index, follow | 56 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/italy` | `/in/italy` | index, follow | 145 | Submitted and indexed | 0 | 16 | d, explore, in | 301 | `/travel-buddies/italy` |
| `/in/jamaica` | `/in/jamaica` | index, follow | 57 | Submitted and indexed | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/japan` | `/in/japan` | index, follow | 113 | Submitted and indexed | 1, 0, 61 | 13 | d, explore, in | 301 | `/travel-buddies/japan` |
| `/in/jordan` | `/in/jordan` | index, follow | 54 | URL is unknown to Google | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/kenya` | `/in/kenya` | index, follow | 56 | Submitted and indexed | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/maldives` | `/in/maldives` | index, follow | 59 | URL is unknown to Google | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/mexico` | `/in/mexico` | index, follow | 142 | Submitted and indexed | 0 | 20 | d, explore, in | 301 | `/travel-buddies/mexico` |
| `/in/morocco` | `/in/morocco` | index, follow | 86 | URL is unknown to Google | 0 | 10 | d, explore, in | 301 | `/travel-buddies/morocco` |
| `/in/nepal` | `/in/nepal` | index, follow | 55 | URL is unknown to Google | 0 | 7 | d, explore, in | Keep, enrich | none |
| `/in/netherlands` | `/in/netherlands` | index, follow | 55 | Submitted and indexed | 0 | 4 | d, explore, in | Keep, enrich | none |
| `/in/new-zealand` | `/in/new-zealand` | index, follow | 57 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/peru` | `/in/peru` | index, follow | 85 | URL is unknown to Google | 0 | 9 | d, explore, in | 301 | `/travel-buddies/peru` |
| `/in/philippines` | `/in/philippines` | index, follow | 86 | URL is unknown to Google | 0 | 9 | d, explore, in | Keep, enrich | none |
| `/in/poland` | `/in/poland` | index, follow | 89 | Submitted and indexed | 0 | 11 | d, explore, in | Keep, enrich | none |
| `/in/portugal` | `/in/portugal` | index, follow | 88 | URL is unknown to Google | 0 | 8 | d, explore, in | 301 | `/travel-buddies/portugal` |
| `/in/singapore` | `/in/singapore` | index, follow | 56 | URL is unknown to Google | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/south-africa` | `/in/south-africa` | index, follow | 62 | Submitted and indexed | 0 | 5 | d, explore, in | Keep, enrich | none |
| `/in/south-korea` | `/in/south-korea` | index, follow | 63 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/spain` | `/in/spain` | index, follow | 60 | URL is unknown to Google | 0 | 7 | d, explore, in | 301 | `/travel-buddies/spain` |
| `/in/sweden` | `/in/sweden` | index, follow | 54 | URL is unknown to Google | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/switzerland` | `/in/switzerland` | index, follow | 58 | Submitted and indexed | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/tanzania` | `/in/tanzania` | index, follow | 59 | URL is unknown to Google | 0 | 6 | d, explore, in | Keep, enrich | none |
| `/in/thailand` | `/in/thailand` | index, follow | 115 | Submitted and indexed | 0 | 12 | d, explore, in | 301 | `/travel-buddies/thailand` |
| `/in/turkiye` | `/in/turkiye` | index, follow | 55 | URL is unknown to Google | 0 | 7 | d, explore, in | Keep, enrich | none |
| `/in/united-arab-emirates` | `/in/united-arab-emirates` | index, follow | 59 | Submitted and indexed | 0 | 7 | d, explore, in | Keep, enrich | none |
| `/in/united-kingdom` | `/in/united-kingdom` | index, follow | 83 | Submitted and indexed | 0 | 10 | d, explore, in | Keep, enrich | none |
| `/in/united-states` | `/in/united-states` | index, follow | 168 | Submitted and indexed | 0 | 21 | d, explore, in | Keep, enrich | none |
| `/in/vietnam` | `/in/vietnam` | index, follow | 89 | URL is unknown to Google | 0 | 10 | d, explore, in | 301 | `/travel-buddies/vietnam` |

## Files that would change (on approval)

* `public/index.php`: a route that 301s the 14 `/in/{slug}` URLs to `/travel-buddies/{slug}`.
* `app/controllers.php` `destination()` breadcrumbs and `views/destination.php`: country crumb to the buddy page where one exists.
* `app/buddy_landing.php`: one helper, country to buddy page, reused by both.
* Tests: a redirect test (301, exact Location, no chain) and a link test (no internal link to a redirected URL).
