# RuinMyTrip backlog

Ordered by how much each item moves growth, retention, interaction or shareability, not by how
hard it is. Anything blocked on Nima is marked and stays visible rather than dropping off.
Last reordered 2026-09-10.

## Blocked on Nima (nothing here can be done from a session)

1. **Cloudflare R2.** The API answers `10042 Please enable R2 through the Cloudflare Dashboard`.
   Until it is on there is no photo upload, and photos are the single biggest thing missing from a
   travel social product. Everything else on this list is worth less than this one.
2. **Instagram, TikTok and Facebook accounts.** `SETUP.md` is written and waiting. A travel network
   with no visual channel has no acquisition loop.
3. **An acquisition channel that points here.** Every `dm_variants.txt` line sends people to
   TrustMyRecord. RuinMyTrip needs its own X account, or its own variant on the existing one.
4. **`gh auth refresh -h github.com -s workflow`.** No token on this machine can push
   `.github/workflows/*`, so CI changes go through the GitHub web uploader.

## Next, in order

1. **Photo posts, the moment R2 is on.** Multi photo trip updates, a photo grid on the profile,
   photos in the feed, and the first image in a trip becoming its share card.
2. **Notifications worth opening.** They fire for everything already; what is missing is rollup
   ("three people liked your review") and an email or push digest for somebody who has not
   opened the site in a week.
3. **Traveler discovery past exact overlap.** "People from my city going where I am going",
   "travelers who have been where I am going next", and saved searches that notify.
4. **The trip page as a page people keep coming back to.** Day by day updates in place, a
   join button that reads as one, and a photo strip when R2 lands.
5. **Reviews that are worth reading in a feed.** Aspect ratings already exist; the feed row shows
   a title and an excerpt and none of the structure underneath it.
6. **A weekly "what happened in your cities" email.** The digest exists; it is not yet about the
   cities somebody saved, which is the only version anybody opens twice.
7. **Communities with something in them.** Rooms exist and are empty; either seed them from
   cities with activity, or hide them until they have members.
8. **Search that finds people.** Suggest covers destinations, places and users separately; one
   ranked result set with faces in it would be the fastest path from a name to a profile.
9. **Performance when this gets busy.** Everything is a straight query today. The notifications
   page is N+1 by target, and /explore ships 100KB of HTML.

## Done (2026-09-10, most recent first)

* City pages lead with the people: faces under the hero, who is going, meetups, and the two
  actions, drawn only when the counts are real.
* Dark mode, from a single palette swap, following the reader's own setting. Every surface in the
  stylesheet is a token now rather than a literal white.
* The homepage stopped arguing with itself: people, community and the ruined question first, the
  research collapsed into one strip.
* Messages carry why the two of you are talking (same city, overlapping days), bubbles, read
  state, and Block moved into an overflow.
* Notifications show faces, and a new one looks new (the page used to destroy that by marking
  everything read before deciding what to draw).
* `/going` became a filterable board: city, month, shareable as a link, board above the form.
* Trip pages say who else will be there then, with a thirteen assertion privacy test.
* `TouristTrip` structured data on dated trips instead of `Article`.
* Onboarding is a numbered sequence that opens with the trip, not eighty four checkboxes.
* Profiles take their cover from the member's own most recent public trip, and Report and Block
  moved into an overflow.
* `/` is the feed for a signed in member: composer, scope switch, rails for date matches, your
  trips, people to follow, meetups. Like, reply and save on every row, with the last two replies
  in place, counted in three queries for the whole page.
* Design system v2: Inter and Fraunces self hosted, one elevation and motion language, focus
  states, form fields. Nav cut from thirteen links to four and a disclosure.
* Trip share cards, and `og:image` on every trip page.
* 208 dashes removed from live copy, kept out by `tests/no_dashes_test.php`.
