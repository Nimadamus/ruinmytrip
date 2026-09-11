# RuinMyTrip: where the build is

Replace stale lines here; do not append history. Last touched 2026-09-10 (second pass).

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

## Migrations added this week

`070` profiles.home_destination_id · `071` trips carry dates/visibility and `going` rows became trips
· `072` posts.trip_id (trip updates) · `073` social indexes · `074` profiles.travel_style ·
`075` a profile row for every member.

## What the product looks like now (2026-09-10)

A signed in member lands on the feed, not on the pitch: composer at the top, a scope switch, and a
rail of people and dates beside it (who is in the same city on the same days, your own trips,
travelers to follow, meetups in your cities). Every row can be liked, replied to in place and
saved; the counts and the last two replies for a whole page cost three queries and one,
respectively, not three per row (`app/feed_home.php`).

A stranger lands on a marketing homepage that leads with people and collapses the research into one
strip, a city page whose first module under the hero is the faces of who is going, and a city
travelers hub that says "nobody yet" once rather than six times.

Design is Inter plus Fraunces, self hosted, with one elevation and motion language, a nav of four
links and a `<details>` disclosure, and dark mode from a single palette swap. Every surface in the
stylesheet is a token: there are no literal whites left, which is the only reason the second theme
is fifty lines.

Trips are the shareable object: a drawn share card (`/card/trip/{id}.png`, public trips only),
`TouristTrip` structured data, and an "Also there then" block that names the other travelers whose
public trip overlaps, behind `tests/trip_overlappers_test.php`.

## House style is a test now

`tests/no_dashes_test.php` fails on an em or en dash in anything under `app/` or `views/` that
reaches the browser. It tokenises, so a dash in a comment is fine; `app/seo.php` is allowed because
its dashes are in the rtrim class that strips one off a truncated title. 208 were live before this.

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

## The signup funnel, as it now stands (2026-09-09)

Prod is 4 users and 185 reviews, all editorial: `stat_community_reviews` is genuinely 0. Four leaks
were closed on 9 September, all live:

* The hero printed "0 Traveler reviews" under the Join button. A zero count is dropped now.
* The city chips summed going + meetups + talk under the heading "Who is going, by city". Each chip
  names its own signal, and the heading only claims travellers when somebody posted dates.
* `require_login()` sent everybody to "Welcome back". A contribution route opens on Join, and the
  join page quotes back whatever they had typed (`rmt_return_is_join_intent`, `rmt_join_intent_line`).
* A first review held for an unconfirmed email stayed a draft forever. Migration 076 marks it and
  confirming the address publishes it (`rmt_reviews_release_held`).
* Place pages show the question box to logged-out visitors; the question rides through the join door
  in the return address. Search lands on `/p/`, so this is where strangers actually arrive.

## Waiting on Nima

Four things, all in BACKLOG.md with the detail: R2 (`10042`, enable it in the Cloudflare
dashboard), the Instagram/TikTok/Facebook accounts, an acquisition channel that points at
RuinMyTrip rather than TrustMyRecord, and `gh auth refresh -h github.com -s workflow`.

## Next, in order

The prioritised list lives in BACKLOG.md and is kept current. In short: R2 and photos the moment
Nima enables it, notification rollup, discovery past exact date overlap, and a weekly email about
the cities somebody saved.
