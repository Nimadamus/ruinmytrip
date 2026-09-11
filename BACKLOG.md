# RuinMyTrip backlog

Ordered by how much each item moves growth, retention, interaction or shareability, not by how
hard it is. Anything blocked on Nima is marked and stays visible rather than dropping off.
Last reordered 2026-09-11.

The product in one sentence, which everything here is measured against: *"I am going to Lisbon
October 3 to 10. Who else is going, what are they doing, what should I see, and who might I
actually want to meet?"*

## Blocked on Nima (nothing here can be done from a session)

1. **Switch R2 on in the Cloudflare dashboard.** Creating a bucket answers `10042` until somebody
   clicks it. Everything on this side is finished and tested: the driver signs SigV4 by hand against
   AWS's published vector, `scripts/storage_migrate.php` moves objects in batches while the site is
   up and verifies each by hash, `docs/STORAGE_R2.md` is the runbook. Photographs work today either
   way, stored in the database.
2. **Instagram, TikTok and Facebook accounts.** `SETUP.md` is written and waiting.
3. **An acquisition channel that points here.** Every `dm_variants.txt` line sends people to
   TrustMyRecord.
4. **`gh auth refresh -h github.com -s workflow`.** No token here can push `.github/workflows/*`, so
   the trip notification sweep rides on a page load rather than a schedule (`app/lifecycle.php`).

## Next, in order

1. **Photo posts straight from the composer.** Uploading happens on a trip, a review, a plan or a
   talk post. One tap from the feed is what makes a travel network visual day to day.
2. **Message requests.** A first message from a stranger should be acceptable or ignorable, and
   should not sit in the same list as a conversation. The ceiling on new threads is in; the
   inbox split is not.
3. **Somewhere to find plans when you have no trip yet.** The joinable rail and the city filters
   both start from dates the member has already published. Somebody who has not posted a trip sees
   none of it, and they are the majority of new accounts.
4. **Notification rollup.** They fire correctly, one per event. "Three people liked your review"
   is the version nobody turns off.
5. **A weekly email about the cities somebody saved.** The digest exists and is generic.
6. **Seasonal and practical answers on a city page.** Weather bands, what is closed when, what a
   week costs. Real sources only, and none of it invented.
7. **Related destinations.** "People going to Lisbon also go to Porto" is a real query over real
   trips and would make every city page a doorway rather than a leaf.
8. **Search that ranks across types.** It searches everything and presents nine separate lists;
   one ranked list with faces in it is the fastest path from a name to a person.
9. **Repeat attendance.** Turning up once is the strongest signal on the site and nothing follows
   it: no reminder the day before, no "you were both at this", no second invitation.
10. **Performance when this gets busy.** Notifications are N+1 by target, `/explore` ships 100KB,
    and the feed ranking loads every follow and save for the member on each page.

## Done (2026-09-11, later)

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
