# Crawl blocks, 2026-10-01 (Q6, approved)

Documented before robots.txt changed. Source: full crawl 2026-10-01 01:36 to 02:16 PDT (8,039 URLs
fetched, 4,520 queued) and Search Console pages report, 90 days. **No URL with a query string earned
a single impression in 90 days**, and none of these patterns is in a sitemap.

| Pattern (robots.txt) | What it is | Seen in crawl | Current robots / canonical | Why it is waste |
|---|---|---|---|---|
| `Disallow: /review/new` | "Write a review" form, one link per place | 3,093 | 302 to /login when signed out | A redirect to a login page, linked 20,302 times |
| `Disallow: /login?` | Sign in with a `return=` address | 2,045 | index, canonical /login | Same 27 word page, 796 duplicate titles |
| `Disallow: /register?` | Join with a `return=` address | 795 | index, canonical /register | Same page, 657 duplicate titles |
| `Disallow: /p/*/correct` | "Suggest a correction" form per place | 1,389 | noindex, canonical to the place | A form, never a result |
| `Disallow: /d/*?` | City pages and their sub pages with `?ask=`, `?sort=`, `?type=`, `?cat=`, `?all=` | 2,509 | noindex or canonical to the clean URL | Sorted/filtered duplicates of clean URLs that stay crawlable |
| `Disallow: /plan?` | Trip form with `cta=`, `d=`, `buddy=` | 262 | noindex | Form with tracking parameters |
| `Disallow: /trip/new` | Trip form | 92 | redirect to sign in | Form |
| `Disallow: /buddies?` | Buddy search filters | 87 | canonical /buddies | Filtered duplicates; /buddies/cruise etc are paths and stay open |
| `Disallow: /talk?` | Talk filtered by city or sort | 25 | noindex | Duplicates of /talk |
| `Disallow: /ruined?` | Warnings filtered by city | 14 | noindex | Duplicates of /ruined |
| `Disallow: /reviews?` | Reviews filtered/sorted | 11 | canonical /reviews | Duplicates |

Not blocked (deliberately): every clean path (`/d/{city}`, `/d/{city}/places`, `/p/{venue}`,
`/login`, `/register`, `/buddies`, `/talk`), all sitemap URLs, `/media`, `/card` (share images
must stay fetchable for og:image), CSS/JS/fonts.

Also: `rel="nofollow"` on the per place "write a review" links in `views/_place_card.php` and
`views/place_show.php`, the bulk of the 20,302 links.

Rollback: delete the added lines from `public/robots.txt`. Google rereads robots.txt within about
a day.
