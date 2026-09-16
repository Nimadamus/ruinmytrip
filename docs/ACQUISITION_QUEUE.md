# Acquisition queue

The working state of the acquisition effort. Updated as things move. Last touched 2026-09-15 (Pacific).

**The only measure that counts:** real human visits, signups, confirmed accounts, trips with dates,
and any two of those trips overlapping in one city.

## USER ACTION REQUIRED

**Two accounts unlock almost everything. The full ranking is in `docs/SOCIAL_ACCOUNTS.md`.**

1. **A personal Facebook account in three travel groups.** Five finished posts, fifteen minutes.
2. **A TikTok account**, if somebody will film. Ten scripts written, and it is the only channel where
   reach does not need an existing audience.
3. **Decide on `docs/EDITORIAL_QUESTIONS_PROPOSAL.md`**: ten questions asked openly by the site, on
   the cohort cities. Yes or no. Nothing posted without it.
4. **Decide on the three missing emails** in `docs/ACTIVATION_AUDIT.md`: a match appearing, a
   connection request, and an acceptance all write an in app notification and send nothing.

**The old list, unchanged in substance:**

0. **Post one Facebook group post.** Five are ready in `docs/ACQUISITION_FACEBOOK.md`, each with its
   group type, tracked link, CTA, threshold, whether the link belongs in the body or the first
   comment, and the disclosure line. Start with F1 or F3, both low risk. **Why not from here:** the
   only Facebook asset on this machine is the TrustMyRecord Page, a sports betting brand, whose Page
   access is broken and which cannot post into travel groups anyway.
0b. **X:** two posts written. The only account here is **BetLegend**, a sports betting brand.
   Posting travel content from it would be off brand and would risk an unrelated account, so it is
   queued rather than published.
0c. **TikTok or Reels:** ten scripts written in `docs/ACQUISITION_VIDEO.md`. No account exists. This
   is the one channel that needs no existing audience.


Each of these is finished except for one action only a person can take. **None of them blocks the
rest of the work.**

1. **Post one Reddit thread, starting with r/Munich.** Oktoberfest runs 19 September to 4 October,
   which is four days away and the only cohort that does not involve waiting. Twenty finished posts
   are in `docs/ACQUISITION_PACKAGES.md`, links tracked, landing pages verified. **Why it cannot be done here:** Reddit answers 403 to every
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
  content ideas, six cohorts with dates verified against primary sources.
* **Destination QA**, eight priority cities at 390px and 1280px, fourteen checks each: status, page
  title, social title, card image, canonical, robots, community block, the line for a cold visitor,
  Follow, Ask, composer, share control, travelers link, a reachable trip form, overflow, tap targets
  and JavaScript errors. **112 checks, zero failures.**
* **The pre filled trip link works from cold.** `/trip/new?destination_id=42&date_from=…&date_to=…`
  sends a signed out visitor to signup with the whole link preserved and returns them to a form with
  the city and both dates already in it. Destination ids: Munich 42, Lisbon 2, Chiang Mai 39,
  Miami 85, Bangkok 15.

* **The Oktoberfest campaign path is live on production.** Verified 2026-09-15 on
  `https://ruinmytrip.com/d/munich-germany?utm_source=reddit&utm_medium=post&utm_campaign=oktoberfest`:
  the `cc-window` line renders with the real window, and its button is
  `/trip/new?destination_id=42&date_from=2026-09-19&date_to=2026-10-04`. A signed out visitor is sent
  to signup and returns to that same filled form. The line appears only on the campaign's own city.

* **Two more campaign windows live and verified on production, 2026-09-15.** New Year in Bangkok
  (27 December to 2 January, destination 15) and Carnival in Rio (5 to 13 February 2027,
  destination 47). Both show the window and the prefilled trip link only to a visitor who arrived on
  that campaign. Rio's dates were checked against a Carnival source and against the Easter calendar,
  which puts Ash Wednesday on 10 February either way.
* **Attribution re-verified against live production rows, 2026-09-15.** `/cron/funnel` returns
  per campaign rows for `oktoberfest`, `miami-art-week`, `rio-carnival` and `qa`, each with its own
  source. The 29 sessions on `rio-carnival` are this session's own verification requests and are
  counted as **0 human**, which is the check that matters: automated traffic cannot inflate a
  channel's conversion rate. Live totals: 28 likely human visits in 30 days against 4,252 raw.
* **Six cohorts fully specified**, each with dates, audience, landing URL, tracked URL, channel,
  campaign message, traveler use case and launch date, in `docs/ACQUISITION_COHORTS.md`.
* **Twenty posts written**, the last five covering New Year and Carnival, in
  `docs/ACQUISITION_PACKAGES.md`. None posted.

* **The Oktoberfest window is now shown to search traffic too, live and verified 2026-09-15.** The
  Munich page mentions Oktoberfest ten times and, until this change, offered an organic visitor
  nothing four days before it starts. `rmt_acq_window_near()` shows the window and the prefilled
  form once it is within thirty days, campaign or not. Verified on production: Munich carries the
  line with no UTM at all and the link is `/trip/new?destination_id=42&date_from=2026-09-19&date_to=2026-10-04`;
  Lisbon, Berlin and Bangkok carry nothing. **The nine title experiment cities are excluded by name**,
  so the SEO test running until 2026-09-29 is untouched.

* **Attribution proved again, 2026-09-15, seven arrival shapes.** On production, in seven browsers
  that had never seen the site: reddit post, facebook group, x post, a member share link, direct,
  search by referrer, and an unnamed referring site. All landed 200, all held the first touch cookie
  except direct which correctly has no source, and **Googlebot was given no attribution cookie and
  wrote no row at all.** On the dev server the same shapes were driven through two parameterless
  pages and into signup: every event in each journey still named the channel from the first request,
  and three pageviews stayed inside ONE journey rather than inflating into three.
* **A crawler filter finding worth keeping:** `curl/` is in the crawler list, correctly, so the
  first version of that check measured the filter rather than the funnel. Every scripted check now
  sends a browser user agent.
* **Acquisition command centre live.** Human visits, signups, confirmations and trips per channel
  over 24 hours, 7 days and the window, on `/admin/funnel` and as `command_center` in the key gated
  JSON. Automated traffic excluded from every number, raw sessions shown in brackets beside it.
* **Referral loop.** Every outbound share link carries its channel, `utm_medium=share` and a
  member-share campaign, so a link a member sent is never counted as something we published.
  Facebook added. On your own trip the control reads Invite a traveler.
* **Cold visitor QA, five destinations, phone and desktop, signed out and signed in.** Found and
  fixed the same shared problem on all five: the link to who is going sat around 6,400 pixels down a
  phone page and the only trip control was inside the collapsed menu. Both are now in the actions row
  at the top of the community block, between 999 and 1,482 pixels on a 390px screen.
* **Activation audit.** No required fields on the trip form beyond a city and dates; the destination
  and both dates survive signup; a trip typed before the address is confirmed is **held and written
  the instant it is confirmed** rather than discarded; and confirmation lands on the filled form.

* **The whole chain closed, 2026-09-16.** Campaign landing to a created trip, driven end to end:
  the Munich page with the campaign line, a signed out click on the prefilled button, a signup page
  that named the city and both dates, signup returning **straight to the filled form** rather than a
  welcome page, and the trip written with `trip_created` carrying `facebook/group/oktoberfest`,
  landing on `/matches?new=…`. Every link in that chain is now verified rather than assumed.
* **All seven campaigns validated on production, zero failures.** Page, campaign line with the right
  window, prefilled dates, purpose built social card, canonical and robots untouched, and the signed
  out click reaching signup with the link preserved.
* **Daily line live** at the top of `/admin/funnel` and as `daily` in the key gated JSON: human
  visits, signups, confirmed, trips, top source, top campaign, top landing page, best conversion and
  the change against the weekly average. No rate is reported with fewer than five human sessions
  behind it.
* **Channel work:** five Facebook posts (`docs/ACQUISITION_FACEBOOK.md`), ten video scripts
  (`docs/ACQUISITION_VIDEO.md`), the weekly operating plan (`docs/ACQUISITION_WEEKLY.md`) and the
  ranked channel research (`docs/ACQUISITION_CHANNELS.md`).

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

**Counted only from 2026-09-16 08:20 UTC**, which is after the last verification run that predated
the self check marker. Before that boundary the traffic is ours and is labelled rather than rewritten.

1. **100 genuine external human visits.** Currently **0**. Human sessions arriving with no channel we
   can name are shown separately and not counted: with nothing published anywhere there is no
   external link for somebody to have followed, so a direct session cannot be told from the automated
   floor. When a real link goes out this becomes obvious, because the named channels will move and
   that number will not.
2. **25 real signups.** Currently **0**.
3. **10 real trips with dates.** Currently **0** inside the clean window, 1 all time.
4. **Two travelers who actually connected.** Currently **0**, and it has never happened.

For context rather than credit: in the last 7 days the site saw roughly 1,200 sessions classified
automated and 4,700 classified uncertain. That is the floor this product is trying to be heard over.

Nothing on this list moves without somebody posting a link where travelers are.
