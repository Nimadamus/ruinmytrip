# RuinMyTrip V1 status

A travel discovery and review site: real places with real addresses and opening hours, editorial
research that is labelled as ours, and the machinery for travelers to review what they actually
found. Built to be honest before it is big — which is why the number that matters most in section G
is still zero, and says so on the site itself.

## A. What is live

`https://ruinmytrip.com`, on Render, deployed from `main` on push. Postgres in production, SQLite
for local development and the test suite. PHP 8.3, front-controller routing, a forward-only
additive migrator. No framework and no build step: a deploy is a `git push`.

## B. Core user features

**Find something.** Search across seven entity types, autocomplete with real ranking (exact, alias,
prefix, token, substring, fuzzy), destination hubs, category browsing, neighborhood pages, nearby
by distance, and similar places scored on what the entities have in common.

**Read about it.** Place pages carry address, neighborhood, phone, website, opening hours, price
band and an open-now state, each shown only when we hold it, with the source and the date it was
last checked. Editorial research is labelled wherever it appears and never counted as a traveler
review.

**Contribute.** `/contribute` searches for somewhere you went and takes you straight to writing.
Reviews carry a rating, per-category subratings, traveler type and up to six photos. Intent
survives login *and* signup; a half-written review survives a refresh; publishing without a
confirmed email saves a draft rather than discarding the text. Reviews can be edited.

**Take part.** Helpful votes, reporting, travel lists holding both places and cities, saved places,
following, an activity feed of genuine activity only, and profiles carrying reviews, photos,
destinations, lists and earned badges.

**Tell us we are wrong.** Suggest a missing place, or a correction to any place page — no account
needed, and nothing submitted changes the site on its own.

## C. Admin features

Place editor with attributes, hours, photos, status and rename; the place lifecycle (open,
temporarily closed, permanently closed, hidden); a corrections and feedback queue; missing-place
suggestions; review moderation with an audit log; the contribution funnel; destination depth and
gaps; and SEO readiness with a verdict and a reason for every entity.

## D. SEO and discovery foundation

One function decides what is indexable, and robots, canonical and sitemap inclusion all derive from
it — a page that says noindex cannot be in the sitemap. A sitemap index with cached, partitioned
children split at 5,000 so the partitioning path is the one that runs daily. Structured data on
places, destinations, profiles and articles, with no `aggregateRating` anywhere until there is a
community rating to report. Six category landing pages are live as a controlled pilot, with the
threshold at six qualifying places and no page created below it.

## E. Trust and moderation

A report is not a verdict: nothing reads a report count, there is no threshold, and nothing in
moderation reads a rating — a one-star review is held to the same rules as a five-star one. Hiding
a review removes it from every aggregate and restoring returns them. Content is never deleted
outright, and every decision records who made it and what the state was before.

Editorial and traveler content are separated by role, by label, by query and by presentation. The
editorial account earns no traveler badges and its profile says "places covered", never "visited",
because we have never claimed to have gone.

## F. Data sources and provenance

Place data comes from OpenStreetMap (ODbL, attributed) and official venue pages, applied through a
matcher that refuses what it cannot verify — it has vetoed a car charger posing as a hotel and a
museum in Texas standing in for one in New Orleans. Every enriched place names its source and its
checked date. Nothing is scraped from Yelp, Tripadvisor or Google.

## G. Current content counts

Live numbers, read from production on 2026-08-29. `/admin/seo` and `/admin/funnel` are the running
versions of this table.

| | |
|---|---|
| Destinations | 84 |
| Places | 123 |
| Editorial reviews | 185 |
| Guides | 80 |
| Blog posts | 17 |
| Neighborhoods (curated) | 67, with 139 alias spellings |
| Indexable URLs in the sitemap | 599 |
| SEO pilot pages live | 6 |
| **Traveler reviews** | **0** |
| Registered travelers | readable at `/admin/funnel` |

Zero traveler reviews is the honest state and the site says so on the homepage, on every place
page, and on `/about`. It is an acquisition problem, not an engineering one.

## H. Known non-blocking limitations

- **No traveler reviews yet.** Every ranking module is dormant, truthfully, and wakes up on its own
  when three distinct travelers review one place.
- **Thin inventory.** 123 places across 84 destinations, roughly one per city. Hotels are the
  weakest category: only five destinations have any.
- **No marketing-email consent flag.** Transactional mail works; there is no campaign
  infrastructure, deliberately.
- **No published support inbox.** Contact routes through in-product forms into the same queue.
- **Email verification cannot reach real users** without a verified sending domain, so publishing
  holds a draft rather than failing.
- **Production Postgres is not reachable externally** (empty IP allowlist). Live numbers are read
  through the admin pages.
- **Three place pages carry no attributes** where OpenStreetMap could not be verified, one of them
  deliberately: Ondine in Edinburgh is tagged disused and we will not publish hours for a
  restaurant that may have shut.

## I. Post-V1 priorities

1. **Get the first genuine traveler review.** `docs/ACQUISITION.md` has the channels and the rules;
   `docs/FIRST_REVIEW_PLAYBOOK.md` has what to check when it arrives.
2. **Watch the six-page SEO pilot in Search Console.** Baseline in `docs/SEO_PILOT_BASELINE.md`.
   Expand only on evidence.
3. **Expand inventory where the gap report says it is thinnest** — hotels first, then restaurants
   in the strongest destinations.
4. **Write guides for destinations that have places but nothing written about them.**
5. **Grow destination coverage on demand** rather than in bulk.

Do not start these because the list exists. Start them when the evidence or the decision does.
