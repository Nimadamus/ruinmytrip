# RuinMyTrip: where the build is

Replace stale lines here; do not append history. Last touched 2026-09-15 (tenth pass).

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
`077` photos carry an owner and a status · `078` profiles.open_to_meeting · `079` profiles.cover_key ·
`080` profile interests · `081` trip_activities · `082` activity_requests (join lifecycle, capacity,
meeting point, end time, cancellation, activity photos) · `083` trip_members (collaborative trips) · `084` place source ids and aliases · `085` indexes for
the reads this product actually does · `086` provider kind on a place, and a stadium category ·
`087` who said these opening hours.

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

## The city page is a community page (2026-09-15)

A city opens with its people now, not with our opinion of it. `views/_city_community.php` sits
directly under the hero: Follow the city, a strip of counts that are all queries and all drawn
only above zero, a composer that posts into the city and lands the reader back in the thread, six
recent questions with author and timestamp, the photographs, and an honest empty state for a city
nobody has spoken about. The old buried "Travelers talking" section and the photo wall moved into
it; the rating, editorial review, places, reviews, trips, map and related cities are untouched and
one section lower. No route, canonical, robots rule or sitemap entry changed. Guarded by
`tests/city_community_test.php`.

## The traffic numbers count people now, 2026-09-15

The dashboard's top line was every signed out session that reached an indexable page, and on this
site that was overwhelmingly automated: 8,279 sessions of which 8,073 lasted zero seconds, against
zero search clicks in the same window. The cause is our own markup: /register and /review/new are
linked from every place and destination page, a signed out fetch of either records an event, and a
cookieless client mints a new session per request, so sessions counted requests.

Migration 093 adds `contribution_events.cookied`: did this request arrive carrying a token we had
already set. `rmt_traffic_shape()` in `app/traffic_shape.php` classifies each session human,
automated or uncertain, and `rmt_growth_funnel()` leads with the human count while publishing the
raw one and the other two buckets beside it. One page and nothing after it stays UNCERTAIN for
good: a bored person and a polite crawler are the same row.

Nothing was deleted and no row was rewritten. Rows from before the bit carry null and are never
called automated on a signal they never had. Read the numbers without an account at
`/cron/funnel?key=CRON_KEY&days=0`, which now carries the social funnel, the stages with their
conversions, communities, attribution, sources and the traffic shape.

## Messaging is mutual opt in, 2026-09-15

`rmt_message_allowed()` in `app/messages.php` is the whole policy and the only place it lives.
messages_send() asks it before it reads the request body, so a hand made POST gets the same answer
the page does. A block beats everything. A conversation that already holds messages stays open,
because this tightened something that used to be open to anybody and cutting live threads would
punish people for a policy they had no part in. Otherwise both sides must have an accepted connect.

The reasons are named rather than boolean: none, requested, incoming, declined, accepted, existing,
blocked, self. The thread footer draws one of them: waiting on them, an answer they can give right
there, a no that is final, or the composer. The inbox lists accepted connects with no thread yet
under "You both said yes" with a Start conversation button and the trip that brought them together.

Messaging telemetry is `message_sent` and `message_thread_viewed`, both called with no arguments at
all, which is the simplest guarantee that no private message can reach an analytics table.

## Somebody learns their dates were landed on, 2026-09-15

`rmt_match_notify()` existed and was wired only to the old `/going` form, so every trip posted
through the form people actually use told nobody. It now fires from `rmt_trip_create_row()`, which
means the held-until-confirmed path notifies too, and from an edit in both directions: moving out
of everybody's way takes back the unread rows (`rmt_match_notify_clear`), moving back in tells them
again. Restraint is the point: one per recipient per trip ever, one per pair per city while the
first is still unread, nothing if either side said they are not looking to meet, nothing for a
followers only or private trip, nothing across a block. A read row is never deleted.

The reader meets it in three places: one dismissible line at the top of the feed (dismissing marks
the notification read, so the two cannot disagree), the notifications list, which now reads the
trip from `trips` rather than the legacy `going` table and so finally says which city, and
`/matches`.

`app/connects.php` and migration 092 add the deliberate signal between them: "Interested in
meeting", one row per (trip, sender) enforced by a unique index. It carries no words, discloses
nothing either side has not published, enrols nobody, and a no is final. Messaging between
strangers is gated on `rmt_connect_mutual()`.

## The first session, 2026-09-15

Post a trip and the next screen is the people it just put you in front of: `/matches?new={id}`,
which names the trip back to you and then answers it. Measured at about five seconds from landing
on a city page to standing in traveler discovery, on a phone.

A trip is still a city and two dates. Migration 091 adds two optional answers to one:
`travel_style` (the same four words a profile uses, not a second vocabulary) and `open_to_meeting`,
which has three states and the third is the point. Null is unstated and behaves exactly as the site
always has. Zero is somebody saying they do not want to be introduced, and it holds in the overlap
list, the near miss list, the "have actually been" faces on the matching page, and the notification
that goes out when a trip is posted. Their trip stays as visible as they set it; they are simply
not offered as somebody to meet.

`/matches` is one block per upcoming TRIP, not per city: keyed by trip id because somebody with two
trips to Bangkok had the second one's near misses drawn under the first one's dates. Near misses
(`rmt_trip_near_misses`) are travelers in the same city within a fortnight either side, labelled by
which side and by how many days, never drawn as an overlap. An exact duplicate trip is refused and
lands on the one that already exists.

## What is measured, since 2026-09-15

`app/contribution_events.php` holds both funnels. The review one was always there; the social one
is new and leads `/admin/funnel`: a city page opened, follow pressed and follow stuck, composer
focused and question posted, posts, comments, reactions, signup seen, started and finished, sign
in, profile read, profile filled in, trip form opened, trip created, overlapping travelers seen,
first message, return visit. Migration 090 adds `visitor`, sixteen random characters in a first
party cookie, which is the only way "did they come back" can be answered and is not made out of
the person. The journey token already linked one session's steps, which is what makes signup
attribution work: the city seen in the same session as the account being created is the city that
recruited them.

Two traps that cost an afternoon and are now tests. `rmt_track()` returns whether it wrote, and
`rmt_track_once*` only spends the session marker on a row that was actually written, because a
crawler visit used to deafen a session to an event permanently. And a return visit is decided once
per session from the cookie as it arrived, because the cookie is written on the first page of a
first visit and is present by the second.

## What the product does now

A member lands on a ranked feed with a composer, an expiring "how was it?" card above it, and rails
(plans you could join, dates that overlap yours, your trips, people to follow). A trip can be just a
city and two dates and names itself, and on it is a plan: a line of typing and a day, with time,
place, notes, who may come and who may see it behind one disclosure.

A plan is the social object. It has its own page with photographs, who is coming, a short
coordination thread, a meeting point only the people accepted can read, and a join lifecycle that
runs ask, accept, decline, withdraw, remove, cancel. `/meetups` shows meetups and open plans as one
list, interleaved by day, because they are the same offer. A city page shows what travelers are
doing, narrowed to your dates by default, with two filters: open to join, and a category.

Then the loop closes. The day before, the people meeting are reminded. Once a plan's day has passed
its owner is asked once, two taps, whether it was worth it, and the answer appears on the city page
as a recommendation with a real name and a count of people. If one person said it, it says one
person.

Three emails and only three: an ask to join, a yes, a cancellation. Everything else lives on the
site. A first message from a stranger is a request rather than a conversation, counted separately
and not allowed to light up the navigation. Likes and saves roll up; anything addressed to the
reader personally never does.

A trip can be planned by more than one person. The owner is still `trips.user_id`; editors live in
`trip_members` and may add plans, places and photographs and nothing else. A member sees the trip
whatever its visibility, and that hole is cut once, inside `rmt_plan_visibility_sql()`, so every
list on the site learned it at the same moment.

Files worth knowing: `app/activities.php` (the plan model and every read of it),
`app/trip_members.php` (who may do what to a trip), `app/trust.php` (public facts about an
account, never a score), `app/photos.php`,
`app/discovery.php`, `app/feed_home.php` (rails, engagement, ranking), `app/lifecycle.php` (trip
notifications), `app/storage.php` (R2 driver).

## The safety audit is a test

`tests/safety_test.php` reads every query in `app/` that touches the trips table and fails unless it
filters by visibility, is a single lookup, belongs to one member, is a count, or is allowlisted with
a written reason. Seven visibility leaks were found and fixed on 9 and 10 September: the feed and
`/discover`, the city photo wall, the city page itself, search, tag pages, the search-engine ping
queue, and the traveler directory ignoring blocks. Every one was found by planting a canary and
looking, never by reading the query. Add a query that forgets and the suite goes red.

`tests/adversarial_test.php` is the third: it starts from the attacker's end and tries to get at
what it should not have, so a regression reads as "somebody got in" rather than as an assertion
changing. `tests/query_budget_test.php` holds eight pages to a query count. `tests/vocabulary_test.php`
keeps one word per idea in the text a reader sees.

`tests/pages_render_test.php` is the other half: it signs in and greps rendered pages for planted
canaries. It currently guards a private trip, a private trip's photograph, a private plan, an open
plan sitting on a private trip, and a meeting point (absent for somebody who only asked, present
once they are accepted). Backend rules are not the thing that leaks; pages are.

## Places are real now, and the provider is the thing to watch

Ten cities hold real, checkable places, around 1,150 of them. Every row carries coordinates, a provider record id, the
provider's own word for what it is, a category a reader would use, and an attribution line linking
the record and the licence. No ratings, popularity or reviews ever come from a provider; a
whitelist test fails the build if anybody adds one.

Importing: `scripts/push_places.php` fetches here and posts to the site, `scripts/city_kit.sh
<slug>` does a whole city's mix and is resumable, and `/cron/places` has `verify`, `recategorize`,
`backfill` and `ingest`. Everything is safe to run twice.

Provider resilience, all of it learned the hard way in one afternoon:

* four mirrors, configurable without a deploy, ranked by a health file that records what has been
  answering, with a cooldown that grows to a cap and clears on one success
* an early attempt gets twelve seconds because there is another mirror to try; the last gets
  forty five because there is nowhere else to go
* a retry halves the search radius rather than repeating the question, because in a dense city it
  is the bounding box scan that times out, not the network
* **an empty answer is not believed on one mirror's word.** A regional instance answered a Tokyo
  question with HTTP 200 and zero elements, which reads as "Tokyo has no bars". That is the most
  dangerous failure mode there is because it does not look like one.

How well it works, measured rather than guessed: with one endpoint, roughly one kind in seven
failed. With four mirrors ranked by health, the last three city runs failed zero times out of
sixty six. The workhorse turned out not to be the obvious one: maps.mail.ru answered 64 times
against overpass-api.de's 19, because the ranking moves work to whoever is healthy rather than to
whoever is first in a list.

`scripts/osm_extract.php` is the fallback if this stops being dependable: same ODbL data from a
downloaded Geofabrik extract, same canonical rows, proved against a fixture and deliberately not
wired into production.

## Acquisition, 2026-09-15

A campaign can name a real travel window, and the pages a campaign visitor sees offer it.
`RMT_ACQ_WINDOWS` in `app/acquisition.php` holds six: Oktoberfest, Web Summit, Yi Peng, Miami Art
Week, New Year in Bangkok and Carnival in Rio, each with dates checked against a primary source and
a comment saying what was checked. A visitor arriving on `?utm_campaign=oktoberfest` sees the window
on the Munich page with a button that opens the trip form with the city and both dates in it; a
signed out visitor goes to signup and comes back to that same filled form; after confirming their
email they land on it again, and the empty feed card names the window instead of asking where they
are going. The line never appears on a city the campaign does not name, and nothing creates a trip:
the person still submits it.

Attribution is first touch and survives the whole path, which is why any browser check has to use a
fresh context per campaign. Verified live on 2026-09-15: six campaign paths at 390px and 1280px,
zero failures, and `/cron/funnel` returning per campaign rows. The 29 sessions that verification
generated are all counted as **zero human**, which is the property that matters.

**The operating view is `/admin/funnel` under "Acquisition, now"**, and `command_center` in the key
gated JSON: human visits, signups, confirmations and trips per channel over 24 hours, 7 days and the
window, with automated traffic excluded from every number and the raw session count beside it.

**Sharing is a channel now, not a button.** Every outbound share link carries the channel it left by,
`utm_medium=share` and a member-share campaign, so a link one member sent another is never counted as
something we published. On your own trip the control reads Invite a traveler.

**The empty page is part of the campaign.** Most arrivals will match nobody, so the no match state
says so plainly and then offers four things that are real on that city today: follow it, ask it, see
everyone in it, and invite somebody, with your own trip link when you have one.

**The whole chain is verified rather than assumed.** Campaign landing, signed out click on the
prefilled button, a signup page that names the city and both dates, signup returning straight to the
filled form, and a trip written with the campaign still on it. Driven end to end on 2026-09-16.

**Our own checks do not count as travelers.** Verification campaigns are named in a list, and checks
that have to use a real campaign name carry `utm_content=selfcheck`, which the report subtracts from
that row's human count. The three production checks live in `docs/qa` with their traps written down.

What is not done is the only thing that moves the numbers: nobody has posted a link where travelers
are. Reddit is blocked from this machine, there is no personal Facebook account, and the only X
account is a sports betting brand, so every channel that needs a human account is waiting on one.
The copy for all of them is written: `docs/ACQUISITION_PUBLICATIONS.md` (twenty posts),
`docs/ACQUISITION_FACEBOOK.md` (five), `docs/ACQUISITION_VIDEO.md` (ten video scripts),
`docs/ACQUISITION_WEEKLY.md` (the repeating week) and `docs/ACQUISITION_CHANNELS.md` (ranked, with
what could not be read said plainly). Twenty posts are written in `docs/ACQUISITION_PACKAGES.md`, six cohorts specified in
`docs/ACQUISITION_COHORTS.md`, and the state of the effort is `docs/ACQUISITION_QUEUE.md`. Reddit is
blocked at the network layer from this machine, for reading as well as posting.


## Waiting on Nima

Four things, all in BACKLOG.md with the detail: R2 (`10042`, enable it in the Cloudflare
dashboard), the Instagram/TikTok/Facebook accounts, an acquisition channel that points at
RuinMyTrip rather than TrustMyRecord, and `gh auth refresh -h github.com -s workflow`.

## Next, in order

The prioritised list lives in BACKLOG.md and is kept current. In short: photo posting from the
composer, message requests, and somewhere to find plans for the member who has not posted a trip
yet, which is most new accounts. R2 and photos at scale the moment Nima enables it.
