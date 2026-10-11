# How search engines reach place pages: investigation, 2026-10-11

Question from the owner: why do so many place pages depend on Nearby and Similar links for
discovery, and should we build a crawlable city and place hierarchy, pagination, better internal
linking, or the curated sitemap?

Nothing here has been changed on the site. Each recommendation has its evidence, risk and expected
benefit, and needs approval before it ships.

## Correction first

The earlier report said about three quarters of place pages were reachable only through Nearby and
Similar links. **That was wrong.** It counted links from city pages and the 24 place list only, and
missed the category pages (`/d/{city}/restaurants`, `/things-to-do`, `/hotels`, `/experiences`).
A full crawl that follows every link robots.txt allows says otherwise (below).

## Evidence

**Crawl** (`scripts/seo/crawl_graph.py`, 2026-10-11, 2,229 pages, starting at the homepage,
following only links robots.txt allows under Google's matching rules):

| Measure | Result |
|---|---|
| Place pages reached | 1,393 of 1,393 |
| Indexable place pages | 832 (561 noindex under our own thin page rule) |
| Indexable places linked from a hub (city page, city place list, category page or guide) | 823 of 832 |
| Indexable places linked only from other place pages | 9 |
| Click depth of indexable places from the homepage | 2 clicks: 18 · 3 clicks: 351 · **4 clicks: 463** |
| How each place was first reached | category page 478 · another place page 581 · city place list 249 · city page 82 · guide 3 |
| Place links on category pages that point to noindex places | 528 of 1,286 (41%) |
| Place links on city place lists that point to noindex places | 97 of 331 (29%) |
| Place links on the homepage | 0 |
| Place links on a city page (Paris) | 6, plus links to 4 category pages and 3 areas |

**Search Console crawl stats** (90 days): 4,650 Googlebot requests, about 52 a day, 57% discovery
and 43% refresh, average response 385 ms. Lost place pages were last crawled 9/13. Google spends
very little crawl on this site, so what it reaches first and how often matters more than the size
of the graph.

**robots.txt**: `Disallow: /d/*?` (added 2026-10-01, commit ac5dd43, to stop crawl waste on sorted
and filtered lists) also blocks `/d/{city}/places?all=1`, the full list the code comment says "a
crawler can follow". The category pages make up for most of it, so this is not the main problem.

## What the problem actually is

1. **Depth, not orphaning.** 463 of 832 indexable places are four clicks from the homepage
   (home, city, category, place). With about 52 Googlebot requests a day, most of them for
   robots.txt, sitemaps and assets, a four click page is rarely revisited.
2. **Hubs spend their links on pages we told Google not to index.** 41% of category page links and
   29% of place list links point at noindexed thin places. Those links still cost crawl and dilute
   the strong pages.
3. **No sitemap entry for any place** since 9/21, so the only recrawl signal for places is links.
4. **Site quality is the larger constraint.** The drop of 9/28 was site wide and matches the
   September 2026 spam update (docs/SEO_RECOVERY_20261011.md). Better discovery helps Google find
   and refresh the good pages; it does not by itself lift a site level quality assessment.

## Options, with evidence, risk and expected benefit

| # | Option | Evidence it addresses | Risk | Expected benefit | Recommendation |
|---|---|---|---|---|---|
| A | **Curated places sitemap** (241 strongest pages, `RMT_SITEMAP_PLACES=1`, built and off) | No place in any sitemap; low crawl volume | Low. Own child file, every entry still checked by the indexing rule, one variable to undo | Faster recrawl of the best pages; Search Console reports their coverage separately | Yes, a week after place v2 is live |
| B | **City page links straight to its strongest places** (for example the top 12 indexable places by signals, as a plain list), no new URL | 463 places at depth 4; city page carries 6 place links | Low. Same URLs; adds links on 85 existing pages | Moves the strongest places from 4 clicks to 3 | Yes, small change |
| C | **Hubs list indexable places first and stop linking noindex thin places** (or keep them in a collapsed "places we know little about" list without links) | 41% and 29% of hub links point at noindex pages | Medium. Changes internal linking on 85 to 200 pages; thin places become harder to reach for readers too | Concentrates crawl and link value on the 832 pages that can rank | Yes, measured on a few cities first |
| D | **Show the whole city list on `/d/{city}/places` without a query** (24 cards, then the rest as compact text links on the same URL) | `?all=1` blocked by robots | Low. No URL, robots or canonical change; page grows by roughly 10 KB in the biggest cities | Every place two clicks from its city for readers and crawlers | Optional; B and C cover most of it |
| E | **Paginated lists** (`/d/{city}/places?page=2` or `/places/2`) | Same as D | Higher. New URLs, needs robots and canonical rules, can create thin near duplicate pages | Similar to D | No, D gives the same with no new URLs |
| F | **Allow `/d/*/places?all=1` in robots.txt** | Same as D | Low to medium. The page canonicalises to the list without the query, which Google may ignore for a page with different content | Similar to D | No, D is cleaner |
| G | **Homepage links to cities and places** (part of design preview 1) | Homepage has 0 place links | Low | Places that matter one click shallower | Yes, with the homepage redesign |

## Suggested order and how to judge it

1. Place page v2 live (quality first). 2. B and G. 3. A, a week later. 4. C on five cities
(Paris, Amsterdam, London, Lisbon, Rome), compared with five similar cities for four weeks.

Measure weekly: impressions and clicks for the 241 shortlisted pages against the rest, last crawl
date from URL Inspection on a fixed sample of 30, click depth from a rerun of the crawl, and the
sitemap-places coverage line in Search Console.

Not recommended now: changes to robots.txt, canonicals, titles or URL structure.
