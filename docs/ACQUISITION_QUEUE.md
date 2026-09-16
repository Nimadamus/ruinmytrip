# Acquisition queue

The working state of the acquisition effort. Updated as things move. Last touched 2026-09-15 (Pacific).

**The only measure that counts:** real human visits, signups, confirmed accounts, trips with dates,
and any two of those trips overlapping in one city.

## USER ACTION REQUIRED

Each of these is finished except for one action only a person can take. **None of them blocks the
rest of the work.**

1. **Post one Reddit thread, starting with r/Munich.** Oktoberfest runs 19 September to 4 October,
   which is four days away and the only cohort that does not involve waiting. Ten finished posts are
   in `docs/ACQUISITION_PACKAGES.md`, links tracked, landing pages verified. **Why it cannot be done here:** Reddit answers 403 to every
   unauthenticated request from this machine and blocks the signed in browser with "You've been
   blocked by network security" — tested three ways on 2026-09-15 (curl, the CDP browser, a fresh
   Playwright browser). Subreddit rules therefore cannot be read immediately before posting, which
   is the precondition. Separately, the only Reddit account available is **u/TrustMyRecord**, karma
   1, a sports betting brand name, whose last three posts were removed by Reddit's sitewide spam
   filter. Posting a travel link from it risks the account rather than the post.
2. **Post to one Facebook group.** Four templates ready in the same file. Needs a personal account
   and a group joined for long enough to post.
3. **Instagram, TikTok and X accounts** for RuinMyTrip do not exist. Thirty days of content is
   written and waiting in `docs/ACQUISITION_PACKAGES.md`.

## DONE

* **Attribution end to end.** `utm_source`, `utm_medium`, `utm_campaign` and `utm_content` captured,
  first touch held through the session and a 90 day cookie, verified in one browser from landing
  through signup to trip creation. Referring hosts mapped to a closed list of channels; no referrer,
  address or agent is ever stored. Migration 094, `app/acquisition.php`, 47 test cases.
* **Human classification applied to channels.** Crawlers cannot contaminate a channel's conversion
  rate: every rate divides by human sessions, and a rate with no denominator prints nothing.
* **Acquisition dashboard.** Source, campaign, human visits, signups, confirmations, trips, and
  visit to signup, signup to trip and visit to trip, on `/admin/funnel` and in
  `/cron/funnel?key=CRON_KEY`.
* **Social previews.** City pages preview as "Going to {City}? Meet travelers heading there" with
  the purpose built card, kept in separate fields from the page title so the nine page SEO title
  experiment is untouched.
* **Sharing** on the city community block and on the empty overlap state, with people first wording.
* **Cold landing path.** A first line explaining the site to somebody arriving from a link, and the
  homepage cut to one sentence with the review CTA moved off the hero for signed out visitors.
* **Campaign packages**: ten finished posts across Reddit, Facebook, X and TikTok, thirty days of
  content ideas, five cohorts with dates verified against primary sources.
* **Destination QA**, eight priority cities at 390px and 1280px, fourteen checks each: status, page
  title, social title, card image, canonical, robots, community block, the line for a cold visitor,
  Follow, Ask, composer, share control, travelers link, a reachable trip form, overflow, tap targets
  and JavaScript errors. **112 checks, zero failures.**
* **The pre filled trip link works from cold.** `/trip/new?destination_id=42&date_from=…&date_to=…`
  sends a signed out visitor to signup with the whole link preserved and returns them to a form with
  the city and both dates already in it. Destination ids: Munich 42, Lisbon 2, Chiang Mai 39,
  Miami 85, Bangkok 15.

## IN PROGRESS

* Nothing. Everything that can be done without an external account is done.

## NEXT

* Watch the SEO title test. **First honest read is 2026-09-29**, 14 days after the change; the plan
  is in `docs/SEO_TITLE_TEST_20260915.md`. Do not touch it before then.
* Re-verify attribution weekly against live rows rather than assuming it still works.
* Signup to first trip friction: measure it again once real people are in the funnel rather than
  guessing at it with a sample of two.

## BLOCKED

* **Reddit, from this machine, entirely.** Not only posting: reading. The IP is blocked at Reddit's
  network security layer, so rules research is impossible here as well.
* **R2 photo storage**, still returning `10042` until it is switched on in the Cloudflare dashboard.
  Not an acquisition blocker; photographs work, stored in the database.

## The milestones, in order

1. **100 likely human visits.** Currently 11 over 17 days.
2. **25 signups.** Currently 5 all time.
3. **10 trips with dates.** Currently 1.
4. **Two overlapping trips in one city.** Currently 0, and it has never happened.

Nothing on this list moves without somebody posting a link where travelers are.
