# RuinMyTrip backlog

Ordered by how much each item moves growth, retention, interaction or shareability, not by how
hard it is. Anything blocked on Nima is marked and stays visible rather than dropping off.
Last reordered 2026-09-10.

## Blocked on Nima (nothing here can be done from a session)

1. **Cloudflare R2.** The API answers `10042 Please enable R2 through the Cloudflare Dashboard`.
   Until it is on, there is no photo upload, and photos are the single biggest thing missing from
   a travel social product.
2. **Instagram, TikTok and Facebook accounts.** `SETUP.md` is written and waiting. A travel network
   with no visual channel has no acquisition loop.
3. **An acquisition channel that points here.** Every `dm_variants.txt` line sends people to
   TrustMyRecord. RuinMyTrip needs its own X account, or its own variant on the existing one.
4. **`gh auth refresh -h github.com -s workflow`.** No token on this machine can push
   `.github/workflows/*`, so CI changes have to go through the GitHub web uploader.

## Next, in order

1. **Photo posts, the moment R2 is on.** Multi photo trip updates, a photo grid on the profile, and
   photos in the feed. Everything else on this list is worth less than this one.
2. **Comments inline in the feed.** Liking is one tap and done; replying is what makes a thread. The
   composer pattern is already there, the count is already batched.
3. **Notifications that are worth opening.** Follows, likes, comments, a match on your dates, an
   RSVP to your meetup, gathered and rolled up rather than one row per event.
4. **A real onboarding sequence.** Today `/welcome` asks for places, a trip and follows on one
   screen. Split it: city, dates, three people to follow, done, and land them in a feed that is
   already full because of what they just said.
5. **Profiles that look like somebody.** Cover image, the map of where they have been, trips as a
   timeline, badges that mean something, and the share card already drawn for them.
6. **Traveler discovery beyond exact date overlap.** "Anybody in Lisbon this month", "people from
   my city going where I am going", "travelers who have been where I am going next".
7. **Dark mode.** The tokens are in one block now, so it is a palette swap plus an audit of the
   hardcoded `#fff` in the older views.
8. **A city page that is mostly members.** The travelers hub prints six empty modules on a city
   nobody has posted about yet. One honest invitation beats six "nothing yet" rows.
9. **Trip pages that sell the trip.** Day by day updates, who else is going, a join button, and the
   share card in the page itself rather than only in the meta tag.
10. **Messaging that feels like messaging.** Read state, typing affordance, and a link from every
    match straight into a thread.

## Done recently (kept so the order above makes sense)

* Trip share cards, and `og:image` on every trip page (2026-09-10).
* Design system v2: Inter and Fraunces self hosted, one elevation and motion language, focus
  states, form fields. Nav cut from thirteen links to four plus a disclosure (2026-09-10).
* `/` is the feed for a signed in member: composer, scope switch, rails for date matches, your
  trips, people to follow, meetups. Like, comment and save on every feed row, counted in three
  queries for the whole page rather than three per row (2026-09-10).
* 208 dashes removed from live copy, and `tests/no_dashes_test.php` keeps them out (2026-09-10).
