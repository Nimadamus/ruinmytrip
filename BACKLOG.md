# RuinMyTrip backlog

Ordered by how much each item moves growth, retention, interaction or shareability, not by how
hard it is. Anything blocked on Nima is marked and stays visible rather than dropping off.
Last reordered 2026-09-11.

## Blocked on Nima (nothing here can be done from a session)

1. **Switch R2 on in the Cloudflare dashboard.** Creating a bucket answers `10042 Please enable R2
   through the Cloudflare Dashboard` until somebody clicks it. Everything on this side is finished:
   the driver signs SigV4 by hand and is tested against AWS's published vector, the migration
   script moves objects in batches while the site is up and verifies each by hash, and
   `docs/STORAGE_R2.md` is the runbook. Photographs work today either way, stored in the database.
2. **Instagram, TikTok and Facebook accounts.** `SETUP.md` is written and waiting. The site is now
   visual enough to be worth posting from.
3. **An acquisition channel that points here.** Every `dm_variants.txt` line sends people to
   TrustMyRecord. RuinMyTrip needs its own X account, or its own variant on the existing one.
4. **`gh auth refresh -h github.com -s workflow`.** No token on this machine can push
   `.github/workflows/*`. Until then the trip notification sweep rides on a page load rather than
   on a schedule (see `app/lifecycle.php`), which works and is not where it belongs.

## Next, in order

1. **Photo posts straight from the composer.** Uploading happens on a trip, a review or a talk
   post today. The one-tap "post this photograph" path is the thing Instagram has and this does
   not, and it is the single biggest remaining gap in daily use.
2. **A weekly email about the cities somebody saved.** The digest exists and is generic. The
   version people open twice says what happened in Lisbon this week.
3. **Notification rollup.** They fire correctly and one per event. "Three people liked your
   review" is the version nobody turns off.
4. **Trip pages as a place people return to.** Day by day updates in place, a join button that
   reads like one, and the photo strip.
5. **Search that finds people.** Suggest covers destinations, places and users separately; one
   ranked result set with faces in it is the fastest path from a name to a profile.
6. **Meetups that fill.** Attendance is the strongest signal on the site and the thinnest feature:
   no reminders, no "who else is going" before you commit, no repeat.
7. **Communities with something in them.** Rooms exist and are empty. Either seed them from cities
   with activity, or hide them until they have members.
8. **Performance when this gets busy.** The notifications page is N+1 by target, `/explore` ships
   100KB of HTML, and the ranking layer loads every follow and save for the member on each feed.

## Done (2026-09-10 and 2026-09-11, most recent first)

* Profile covers can be uploaded, and every image picker shows what you chose before you submit.
* Captioned photographs are in the sitemap; uncaptioned ones and private ones never are.
* Two notifications about your own trip (three days before, the day after) and saves that count
  without naming who.
* A city page became a room: here right now, photographs, locals who opted in.
* The feed is ranked by overlap, your cities, saved cities, follows and reactions, and every row
  that moved says why.
* **A private trip was appearing in `/discover` and in followers' feeds. Fixed and tested.**
* Six ways to find people, opt-in locals (migration 078), and reasons on every row.
* Photographs are first class: their own page, captions, likes and replies, grids everywhere,
  ImageObject data (migration 077). The city photo wall was leaking private trips; fixed.
* The R2 driver, the migration script and the runbook.
* `/readyz` reports which migration the database is actually at.
* Dark mode, design system v2, the feed as the signed-in home, discovery, messaging, onboarding,
  trip share cards, and the dash sweep. See git log for the rest.
