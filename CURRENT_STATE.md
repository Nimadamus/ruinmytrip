# RuinMyTrip: where the build is

Replace stale lines here; do not append history. Last touched 2026-09-11 (second pass).

## What the product is

A social travel network. People find it, join, post where they are going, meet the travelers whose
dates overlap, and write reviews other travelers can trust. It is **not** a guidebook: the editorial
content programme is stopped (0 clicks from 1104 impressions in 28 days), and new work goes into
pages made of members.

## The four architecture decisions (Nima, 2026-09-08)

1. **One trip object.** `going` was merged into `trips`; a trip has dates, a visibility and a page.
2. **Honest counters, no score.** Cities, countries, reviews, photos, helpful votes, badges.
3. **Photos move to Cloudflare R2** before Instagram-style posting. **BLOCKED**: the API returns
   `10042 Please enable R2 through the Cloudflare Dashboard`. Nima has to enable it.
4. **App-like mobile bottom bar**, server rendered, phones only.

## Shape of the code

* `app/plans.php` is the one API for trips-with-dates: phase, visibility, validation, upsert,
  `rmt_plan_join` ("I am going too"), `rmt_trip_update_notify`, `rmt_trip_visible_to`,
  `rmt_trip_has_substance`. `app/going.php` is a thin shim over it so old call sites still work.
* `app/travelers_hub.php` builds `/d/{slug}/travelers`: who is going, locals, meetups, questions,
  reviews, and how many travel solo.
* `app/city_watch.php` fans out `city_going`, `city_meetup`, `city_review` to people who saved a city.
* `app/locals.php` resolves a free-text home city to one of ours (`profiles.home_destination_id`).
* `app/feed_scope.php` decides whose activity reaches a feed: people you follow plus cities you saved.
* `app/contribution_events.php` measures both funnels; the join funnel leads `/admin/funnel`.
* `app/cards.php` draws the 1200x630 share card behind `/card/{kind}/{key}.png`, used as og:image.
  Kinds: post, review, c, u, meetup, tag, city and now trip. A trip card refuses to draw anything
  for a trip that is not public, because the route is open to anybody holding the link.

## Migrations

`070` home_destination_id · `071` trips carry dates and visibility · `072` posts.trip_id ·
`073` social indexes · `074` travel_style · `075` a profile row for everybody · `076` held reviews ·
`077` photos carry an owner and a status · `078` profiles.open_to_meeting · `079` profiles.cover_key.

Check what production is actually at with `curl https://ruinmytrip.com/readyz`, which prints the
highest applied migration. A green deploy is not a migration.

## Traps that have already cost outages

* **Dev is SQLite, production is Postgres.** `visited_on` is TEXT and `date_from` is DATE, so
  `UPDATE trips SET date_from = visited_on` and `COALESCE(date_from, visited_on)` pass locally and
  fail live. Both took the site down on 2026-09-09. `tests/driver_sql_test.php` fails on those shapes.
* **A green deploy is not a migration.** Migration 071 rolled back while the deploy went live. Verify
  by querying `schema_migrations`, opening the DB firewall via the Render API and re-locking after.
* **A 200 is not a rendered page.** A view helper typed `string` returning null killed `/feed`
  halfway down with the status already sent. `tests/pages_render_test.php` signs in and greps every
  main route for PHP error text.
* Trips have a visibility now, so anything that lists or links a trip must ask
  `rmt_trip_visible_to()`: the page, the sitemap and the profile photo grid all do.

## Live infrastructure

* Render web `srv-d9co4n0k1i2s73cg0nfg`, **free plan**: it sleeps after 15 minutes, so a Cloudflare
  Worker (`ruinmytrip-keepalive`, cron `*/10 * * * *`) pings `/healthz`. Delete it if the service
  moves to a paid instance.
* Postgres lives on the shared instance `dpg-d9pek12jnfac73ehlo10-a`, database `ruinmytrip`.
* Deploys: `POST /v1/services/{id}/deploys` with `RENDER_API_KEY`, then poll until `status=live`.
* `.github/workflows/*` cannot be pushed from this machine: both `gho_` tokens carry
  `gist, read:org, repo`. Nima can fix it with `gh auth refresh -h github.com -s workflow`.

## Where the numbers are

* `python scripts/gsc_report.py --days 28` for search, `/admin/funnel` for joining and contributing.
* The measure that matters is members who post: signups per week and reviews by distinct travelers.

## What the product does now

A member lands on a ranked feed with a composer and rails (dates that overlap yours, your trips,
people to follow, meetups). A trip can be just a city and two dates and names itself. Photographs
are objects with their own pages, captions, likes and replies. `/travelers` finds people six ways
and says why each one is there. A city page leads with who is there today. Every empty page offers
real cities and real people and invents nothing.

Files worth knowing: `app/photos.php`, `app/discovery.php`, `app/feed_home.php` (rails, engagement,
ranking), `app/lifecycle.php` (trip notifications), `app/storage.php` (R2 driver).

## The safety audit is a test

`tests/safety_test.php` reads every query in `app/` that touches the trips table and fails unless it
filters by visibility, is a single lookup, belongs to one member, is a count, or is allowlisted with
a written reason. Seven visibility leaks were found and fixed on 9 and 10 September: the feed and
`/discover`, the city photo wall, the city page itself, search, tag pages, the search-engine ping
queue, and the traveler directory ignoring blocks. Every one was found by planting a canary and
looking, never by reading the query. Add a query that forgets and the suite goes red.

## Waiting on Nima

Four things, all in BACKLOG.md with the detail: R2 (`10042`, enable it in the Cloudflare
dashboard), the Instagram/TikTok/Facebook accounts, an acquisition channel that points at
RuinMyTrip rather than TrustMyRecord, and `gh auth refresh -h github.com -s workflow`.

## Next, in order

The prioritised list lives in BACKLOG.md and is kept current. In short: R2 and photos the moment
Nima enables it, notification rollup, discovery past exact date overlap, and a weekly email about
the cities somebody saved.
