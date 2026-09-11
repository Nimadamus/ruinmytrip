# RuinMyTrip backlog

Four bands, and the band is the decision: P0 means the product does not work properly without it,
P1 is core product, P2 is growth, P3 is worth doing and not worth doing first. Anything blocked on
Nima is marked and stays visible rather than dropping off. If a band grows past about five items it
is not a band any more, it is a junk drawer with a label, and something has to be cut or demoted.
Last reordered 2026-09-11.

The product in one sentence, which everything here is measured against: *"I am going to Lisbon
October 3 to 10. Who else is going, what are they doing, what should I see, and who might I
actually want to meet?"*

## Blocked on Nima (nothing here can be done from a session)

1. **Switch R2 on in the Cloudflare dashboard.** Creating a bucket answers `10042` until somebody
   clicks it. Everything on this side is finished and tested. Photographs work today either way,
   stored in the database.
2. **Instagram, TikTok and Facebook accounts.** `SETUP.md` is written and waiting.
3. **An acquisition channel that points here.** Every `dm_variants.txt` line sends people to
   TrustMyRecord.
4. **`gh auth refresh -h github.com -s workflow`.** No token here can push `.github/workflows/*`, so
   the trip notification sweep rides on a page load rather than a schedule.

Nothing else is blocked. The place importer needs no key, no account and no payment.

## P0, the product does not work properly without these

1. **Finish the opening hours backfill.** `scripts/backfill_hours.php` asks the provider for the
   objects we already hold, by id, rather than scanning a city again, and puts the answer back
   through the ordinary ingest door. It is running city by city. A batch that times out is simply
   lost until the next run, which is the right behaviour but means it needs a second pass. What it
   cannot fix is coverage: only about one imported place in six carries an `opening_hours` value in
   OpenStreetMap at all, and the parser refuses the ambiguous ones on purpose.

## P1, core product

2. **A destination record for Miami.** It has none, so it cannot have places. Writing a city page is
   editorial work rather than import work, and making the importer happy is the wrong reason to do
   it. Left alone deliberately, as asked.
3. **43 places still have a serial number for a URL** (32 in Tokyo, 11 in Bangkok). The rest were
   moved onto the English name OpenStreetMap already records for them, with the old URL retired
   into `place_slug_history` so it still resolves. These 43 carry no other name at all, and the
   honest options are to leave them, or to add `name:en` upstream in OpenStreetMap, which is
   editorial work on somebody else's database.
4. **Repeat attendance.** Turning up once is the strongest signal on the site and nothing follows it.
5. **One ranked search result list.** City context, category matching and travelers-first all landed;
   the page is still nine lists rather than one ordered answer.
6. **Photo upload during a trip**, beyond one file at a time.

## P2, growth

7. **Events as an object.** The architecture is small; real event data needs a source. Nothing
   invented.
8. **Neighbourhood pages.** Places carry a neighbourhood from the provider and nothing reads it;
   "Alfama" and "6th Arrondissement" are how people actually choose where to stay.
9. **A weekly email about the cities somebody saved.** The digest exists and is generic.
10. **Seasonal and practical answers on a city page.** Real sources only.

## P3, worth doing, not worth doing first

11. **Marker clustering** on a city map. Overlapping dots in a dense centre are hard to tap, which
    is the case where clustering earns its dependency.
12. **The offline extract path**, if Overpass reliability gets worse. Prototyped and tested against
    a fixture; reads nodes only, so a venue mapped as a building outline is missed.
13. **`/explore` ships 100KB** and could ship a third of that.

## Done (2026-09-11, later)

* **Ten cities, 1,211 real places**, no duplicates by name, by source reference or by point, and
  none in the wrong city.
* **Searching by the kind you asked for.** "museum tokyo" used to return the one Tokyo museum with
  an English name; the site knows which of its places are museums and now says so.
* **A backfill that asks the provider for rows by id** rather than re-scanning a city, which is the
  cheapest question there is to put to Overpass.
* **Readable URLs for places with non-Latin names**, taken from a name they really go by, never
  transliterated: the readings ICU gives a Japanese name are Chinese ones.
* **The trip map's day key is the day filter.** Tapping Tuesday shows Tuesday. This was still
  listed as outstanding and had already shipped in a33bea1.
* **Three opening hours forms the parser was wrongly refusing**, found by measuring London rather
  than assuming: a day list with spaces, a span with no day, and midnight as an end time.

* **Eight cities of real places.** Mirror health with failover and cooldown, escalating timeouts,
  shrinking retries, resumable runs, and the fix that mattered most: an empty answer from one mirror
  is no longer believed without a second opinion.
* **Opening hours**, parsed conservatively and refused whole when they carry anything the parser
  does not model.
* **A city page was laying out at 1174px on a 390px phone** and the screen was clipping it.

* **Four cities of real places** (Lisbon, Paris, Rome, Barcelona), categories a reader would use,
  provider kind kept so a mapping decision can be revised without re-fetching, nine shapes of
  malformed record quarantined at the door, retries and backoff, and an audit endpoint.
* **Category browsing** on a city and its places page, **category filtering on the map**, honest
  page titles, schema.org types that match the category, and city context inside the search query.

* **A real place pipeline** (migrations 084, 085): provider abstraction, OpenStreetMap, canonical
  records, source ids, aliases, four-pass deduplication, timid merging, attribution on the page, a
  CLI and an admin button. Proved against the live provider and proved idempotent.
* **Maps** of published things, and only published things.
* **The signed-in home is a dashboard** that leads with the member's own trip.
* **Query budgets** on eight pages, with the counter built into the database layer.
* **An adversarial test suite** written as attempts and refusals.
* **A vocabulary gate**: one word per idea, checked in the text a reader sees.
* **A removed member kept a key to the room**, and does not any more.

* **Collaborative trips** (migration 083). One owner, any number of invited editors, a permission
  split where an editor adds and only an owner publishes or destroys, and the visibility hole cut
  in exactly one place with a test that the SQL and the PHP agree on every trip for every viewer.
* **A plan attaches to a real place**, matched on the normalised name inside the trip's city, and a
  place page carries who has it planned, how many are there while you are, and who would go again.
* **The trip page knows it is the third morning in Lisbon**: a Today card during the dates.
* **Trust signals**: public facts with a link to check them, never a score, next to the join button.
* **Photographs and messages can be reported**, and the report route is a build gate.
* **The feed knows the difference between a city and a date.**

* **The reminder the day before**, to the traveler whose plan it is and the people coming, once
  per plan per person, and never for a plan nobody else is coming to.
* **Travelers here also go to**, a real query over real public trips, counted in people.
* **Plans are in the sitemap** when they have something on them, on the same condition the page's
  own robots tag uses, from the same function.
* **What people are doing, on the city page strangers land on from a search.**
* **Travelers at the top of a search.**
* Notifications stopped being N+1 by plan.

* **A first message from a stranger is a request.** The inbox splits; a request is counted and one
  tap away and does not light up the navigation. Derived from the messages, never stored.
* **A photograph straight from the feed composer**, with the filename echoed so nobody attaches the
  same one twice.
* **A plan has its own share card**, refusing to draw for anything not public.
* **Notification rollup** for likes and saves. Things addressed to the reader personally never roll.
* **Three join emails and only three**: an ask, a yes, a cancellation.
* **Something to join before you have posted a trip**, so a new account sees a live site.

* **The join lifecycle, end to end** (migration 082). Ask, accept, decline, withdraw, remove,
  capacity, meeting point, end time, cancel. The meeting point is for the owner and the people
  accepted, never for somebody who only asked.
* **A plan has its own page**, with photographs, who is coming, a short coordination thread that is
  not a second inbox, and one safety line where somebody is deciding whether to meet a stranger.
* **Plans you could join**, on the feed: open plans in a city the member is going to, inside their
  own dates, that they have not already answered.
* **Two filters on a city, and only two**: open to join, and a category. Both in the URL, both
  built from the plans that actually exist, and a filter can only narrow what the viewer could
  already see.
* **The loop closes.** "How was it?" appears above the feed once a plan's day has passed, two taps
  wide. The answer lands on the city page as "travelers who went say these were worth it", with the
  name of the person who said it and a count of people, never rounded.
* **A meetup and an open plan are one list.** `/meetups` reads both and interleaves them by day.
  Past meetups leave it.
* **People who are coming are told when the time or the place moves**, once an hour at most, and
  nobody else is told anything.

* **Activities: what a traveler is actually doing there** (migration 081). A plan is a line of
  typing and a day; time, place, notes, link, who may come and who may see it are behind one
  disclosure. Plans appear on the trip page, on the city page narrowed to your own dates, in the
  feed, and in search. Others can join where the owner opened it. The same row becomes the
  post-trip answer: did you go, was it any good, would you send somebody.
* A DATE compared to an empty string took the city page down for a few minutes on Postgres.
  Fixed, and `tests/driver_sql_test.php` now refuses the pattern; proved by reintroducing the bug.

## Done (2026-09-11, earlier)

* A trip can be just a city and two dates, and names itself. The form demanded a title and a
  twenty character story, so the sentence the product is built on could not be posted.
* Interests (migration 080), on profiles and in matching, naming what two people share.
* Empty states that are useful and never invent a row, with a test that checks exactly that.
* A trust and safety audit that **fails the build**: every query that reads trips must filter by
  visibility, be a single lookup, or be allowlisted with a reason. It immediately found two more
  leaks (tag pages, the search-engine ping queue).
* **Three more privacy leaks found and fixed**: the city page still showed private trips'
  photographs, search returned private trips by keyword, and the traveler directory ignored blocks.
* A separate, much lower ceiling on opening new conversations.
* The trip page became a page a stranger can land on: countdown, city card with live counts, follow
  the author, what they said about the city afterwards, and more trips to the same city.
* City pages lead with who is there today, the shared photo grid, and locals who opted in.

## Done (2026-09-10)

Photographs as first-class objects with their own pages and privacy; the R2 driver and runbook;
discovery six ways; the ranked feed; dark mode; design system v2; the feed as the signed-in home;
messaging with trip context; onboarding; trip share cards; `/readyz` reporting migration state.
See git log for the rest.
