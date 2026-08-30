# SEO pilot baseline — six category landing pages

Internal. The state of the pilot at launch, recorded so that whatever Search Console reports later
can be compared against something written down before we knew the answer. **Frozen.** Titles, H1s,
URLs, canonicals, thresholds and internal links stay as they are during the observation window: a
baseline that keeps moving measures nothing.

Launched **2026-08-29** (UTC), commit `0e4ebe3` (inventory) and the internal-link commit that
followed. Threshold: `RMT_IDX_CAT_MIN_PLACES = 6`, unchanged.

## The six

| URL | Destination | Category | Qualifying places | In sitemap | Canonical | Verdict |
|---|---|---|---|---|---|---|
| `/d/athens-greece/things-to-do` | Athens | attraction | 8 | sitemap-categories.xml | self | indexable |
| `/d/london-uk/things-to-do` | London | attraction | 8 | sitemap-categories.xml | self | indexable |
| `/d/amsterdam-netherlands/things-to-do` | Amsterdam | attraction | 7 | sitemap-categories.xml | self | indexable |
| `/d/new-york-city-usa/things-to-do` | New York City | attraction | 7 | sitemap-categories.xml | self | indexable |
| `/d/paris-france/things-to-do` | Paris | attraction | 7 | sitemap-categories.xml | self | indexable |
| `/d/paris-france/restaurants` | Paris | restaurant | 6 | sitemap-categories.xml | self | indexable |

All six: HTTP 200, `index, follow`, self-canonical, breadcrumbs Home → Explore → city → page, real
inventory, `BreadcrumbList` only (no invented `ItemList` ratings, no `aggregateRating`), clean at
390px and 1280px.

Counts are **indexable** places, not active rows: a place carrying nothing but a name does not
count toward a threshold it would then say `noindex` about itself.

## How they are meant to be found

Nothing forces indexing. The path is the ordinary one:

- **Internal links** — each city page carries a "Browse by kind" row linking only to the category
  pages it qualifies for; each pilot page links laterally to the city's other qualifying categories
  and to its neighborhoods; every place card links to a place page that links back to the city.
- **Sitemap** — `sitemap-categories.xml`, submitted through `sitemap.xml`, which robots.txt names.
- **IndexNow** — the existing `scripts/indexnow_submit.php`, used as it already is for other URLs.
  Nothing new was built for these six.

## What is deliberately absent

No fabricated ratings, no invented review counts, no "Paris is a beautiful city" introduction. The
inventory is the value; if these pages cannot rank on real venue data with real addresses and real
hours, that is the finding, and padding them would destroy our ability to learn it.

## What to compare later

Per URL: index status (discovered / crawled / indexed), impressions, clicks, CTR, average position,
top queries, and **the canonical Google chose** — that last one is the check on whether
`/d/<city>/places?type=…` is being treated as a duplicate despite pointing here.

Read it with `php scripts/seo_pilot_status.php`, which prints this table live from the database and
production, so the "qualifying places" column can be checked against reality rather than trusted.

The signal that would justify a second batch: pages indexed normally, impressions accumulating on
relevant queries, no duplicate or canonical problems, and some position visibility — first in the
10–50 band, later 5–20. If Google ignores them, find out why before repeating the pattern.

## Not yet, recorded only

Future candidates, in rough order of how close they are: **Rome attractions, Vienna attractions,
Barcelona attractions** (3–5 places each today). Larger gaps needing a real inventory pass: **Paris
hotels (1), Amsterdam restaurants (1), London restaurants (2)**.

Do not expand, and do not lower the threshold, until the six have produced evidence.

## The other engine

These pages currently derive their value from place inventory alone. Production still has **zero
community reviews**. As genuine traveler reviews arrive the same pages get stronger on their own —
ratings, review counts, aspect breakdowns — with no change to any of the code above. That remains
the larger opportunity, and nothing about it is to be faked to make a pilot page look richer.
