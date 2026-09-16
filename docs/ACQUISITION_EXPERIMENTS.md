# Acquisition experiment log

Every real traffic experiment from here on gets a row, written **before** it is published and
completed **after** the numbers are read. Without this we are posting, not learning.

**Where the numbers come from:** `/admin/funnel` under "Acquisition, now" (24 hours, 7 days, window)
and `/cron/funnel?key=CRON_KEY` under `command_center`. Never from memory, never from a guess, and
never from the raw session count, which counts requests rather than people.

**Read it at 24 hours and again at 7 days.** A post's first day is mostly whether it was seen; the
week is whether it did anything.

## How to fill a row

| Field | What it means |
|---|---|
| Hypothesis | What we expect and why, in one sentence, written before publishing |
| Date | Posting date, Pacific |
| Campaign | The `utm_campaign` value, exactly |
| Channel | Where it went, including which subreddit or group |
| Copy | Which numbered item from `docs/ACQUISITION_PUBLICATIONS.md` |
| Landing | The URL in the link |
| Tracking | The full tracked URL |
| Human visits | From the command centre, 7 day column |
| Signups | Same row, `Join` |
| Trips | Same row, `Trip` |
| Outcome | What actually happened, including removal or downvotes |
| Decision | keep / change / stop, and what changes |

## The rules this log exists to enforce

1. **One post at a time per channel.** Two at once and neither result means anything.
2. **Never the same text in two places.** It reads as spam to a moderator and it makes two rows that
   cannot be compared.
3. **A removal is a result.** Record the reason, do not repost there, do not argue with a moderator.
4. **Zero is a result.** A post that got 400 views and no signups tells us the hook worked and the
   landing did not, which is a different fix from a post nobody saw.
5. **Stop after three failures on one channel** rather than tuning the copy a fourth time. The
   channel is the hypothesis, not the wording.

## A warning about the numbers before 2026-09-16

**The `utm_content=selfcheck` marker only started on 2026-09-16 at roughly 00:30 Pacific.** Every
check run before that carried a real campaign name and no marker, so it cannot be excluded now and
will not be: rewriting rows to make a number look better is worse than a number that needs a
sentence.

Concretely: the **37 human sessions on 16 September were almost entirely this project's own
verification runs**, spread across the real campaign names, and only 2 of them are marked. Anything
dated 15 or 16 September in the acquisition table should be read as our own traffic unless a post
was actually published. From 16 September onward the marker does the job automatically.

## Experiments

### E1

* **Hypothesis:** a genuine question in a local subreddit, four days before Oktoberfest, from an
  account that discloses its interest, produces real visits where a link drop would be removed.
* **Date:** not yet published.
* **Campaign:** `oktoberfest`
* **Channel:** r/Munich
* **Copy:** publication 1
* **Landing:** `https://ruinmytrip.com/d/munich-germany`
* **Tracking:** `https://ruinmytrip.com/d/munich-germany?utm_source=reddit&utm_medium=post&utm_campaign=oktoberfest`
* **Status:** **BLOCKED.** Reddit answers 403 to every unauthenticated request from this machine and
  blocks the signed in browser at network security, so the subreddit's rules cannot be read
  immediately before posting, which is the precondition. The only account available is u/TrustMyRecord,
  karma 1, a sports betting brand name, whose last three posts were removed by the sitewide spam
  filter.
* **Decision:** hand to a person with a normal account, or drop the channel.

### E2

* **Hypothesis:** the Munich page now shows the Oktoberfest window and a prefilled trip form to
  **search** traffic as well as campaign traffic, so any organic arrival in the fortnight before the
  event should convert at a measurably different rate from one arriving at a page with no dated call
  to action.
* **Date:** shipped 2026-09-15, measuring from 2026-09-16.
* **Campaign:** none, this is the organic path.
* **Channel:** search and direct.
* **Landing:** `https://ruinmytrip.com/d/munich-germany`
* **Baseline:** 0 search clicks to this page in the 90 days to 2026-09-15.
* **Status:** running. **Confound worth stating:** if the campaign posts never go out, the sample
  here will be too small to read, and the honest answer will be "no data" rather than "no effect".
* **Decision:** read on 2026-10-05, after the window closes.

### E3

* **Hypothesis:** a signup page that names the city and the dates somebody just chose converts
  better than one that says only "Join RuinMyTrip", on the campaign path where the visitor arrived
  for one specific thing.
* **Date:** shipped 2026-09-16.
* **Campaign:** all of them, this is on the path itself.
* **Change:** clicking "Post your Munich dates" now reaches a signup page that says "Join and post
  your Munich dates, 19 September to 4 October. They are already filled in, and you will see which
  travelers overlap them."
* **Baseline:** 1,303 join form views against 2 submissions in the 30 days to 2026-09-15, though
  almost all of those views were automated.
* **Status:** running. **Read it when there is real traffic**, not before: with 2 signups in the
  window there is nothing to compare.

### E4

* **Hypothesis:** the two controls a stranger came for, see who is going and post your dates, were
  around 6,400 pixels down a phone page. Putting them at the top will change the share of arrivals
  that do anything at all.
* **Date:** shipped 2026-09-16.
* **Measured before:** travelers link at 6,410px on a 390px screen, no visible trip control at all
  for a signed in visitor on a phone.
* **Measured after:** 768px and 820px, zero failures across five destinations at two widths.
* **Status:** running. Read against `destination_follow_click` and `trip_create_started` per human
  session once real traffic exists.

### E5

* **Hypothesis:** every page type that earns a search impression had no link to the social product,
  and place pages plus guides carry roughly four times the impressions of destination pages. Adding
  one shared component should change the share of search arrivals that reach a community page.
* **Date:** shipped 2026-09-16.
* **Baseline:** 0 clicks from 1,469 impressions in the 90 days to 2026-09-16, average position 45.
* **Status:** running. **Read it honestly:** with zero clicks in ninety days the sample may never
  arrive, and the right conclusion then is "no data", not "no effect".

### E6

* **Hypothesis:** the milestone counter was reading 41 of 100 while nothing had been published
  anywhere, which cannot be true. Counting only channels we can name should make it read close to
  zero and stay there until a real link goes out.
* **Date:** 2026-09-16.
* **What was found:** 11 of the 41 were this session's own campaign validation, made minutes before
  the self check marker existed. The other 30 arrived direct, out of 778 direct sessions in a day.
* **Outcome:** direct is shown beside the milestone and not counted toward it. **This is the check
  that matters when a link finally goes out:** the named channels will move and direct will not.

### E7, the Facebook Page launch, 2026-09-16 (Pacific)

* **Page:** Ruin My Trip, `https://www.facebook.com/profile.php?id=61594217710086`, category Travel
  Company, bio as specified, profile image the site mark, cover "Two people. One city. The same
  week.", website link tracked `utm_campaign=page&utm_content=profile_link`. Facebook rejected
  "RuinMyTrip" as one word with internal capitals, so the display name is "Ruin My Trip". Phone,
  email, address and WhatsApp left empty on purpose: the admin profile is a personal account and none
  of its details belong on the Page. "Invite friends" was declined for the same reason.
* **Seeded:** five posts, each confirmed live after publishing and none duplicated. Intro (pinned,
  `page/intro`), Oktoberfest (`oktoberfest_2026/fb_post_munich`), Bangkok
  (`new-year-2027/fb_post_bangkok`), a solo travel question with no link, and how overlap works
  (`page/fb_post_howitworks`).
* **Group test: NOT POSTED.** Ten groups read, none permits an unapproved project post:
  * Oktoberfest 2026 (6.9K): bans self promotion, and is about Blumenau in Brazil, not Munich.
  * Oktoberfest 2026 (308): no written rules, calls itself "the official site". Ambiguous, skipped.
  * Munich Travel Tips (6.6K): no written rules, 162 posts a day. Ambiguous, skipped.
  * NEW in MUNICH (77K): "no unauthorized spam". Needs admin authorization.
  * FIND A TRAVEL BUDDY (80K): bans self promotion.
  * TripMates (670K): rule 1 requires "verifying" on an unrelated outside site. Scam signal, avoided.
  * Solo Travelers Find a Travel Buddy (17K): no written rules. Ambiguous, skipped.
  * Munich Oktoberfest (90K, the largest English one): "no ads or spam", paid ads by email only.
  * **Let's Go Solo (4.2K):** rule 6, "Discuss, don't promote (without approval)", and it invites
    contacting the admins, who set aside dates for approved promotional posts. **The only compliant
    route found**, and it needs a message to the admins first.
* **Admin request sent.**
  * **Time sent:** 2026-09-16 03:57 PDT.
  * **Admin contacted:** Rachael Taplin, main profile `facebook.com/rach.taplin` (Founder at Media
    Matchmaker). One message only. The second admin profile was not messaged.
  * **Group:** Let's Go Solo, `facebook.com/groups/letsgosolo.community`, 4.2K members.
  * **Campaign:** `oktoberfest_2026`, group post content tag `group_test_1`.
  * **Sent from:** the personal profile that administers the Ruin My Trip Page, because a Page cannot
    start a conversation. The text was the approved wording, with the dash removed.
  * **Status: awaiting approval.** No group post until she says yes. No follow up unless she replies.
  * Messenger on that account required choosing between a chat history PIN and not restoring old
    chats. With the owner's go ahead, "Don't restore messages" was chosen; no PIN was entered.

### Template for the next one

* **Hypothesis:**
* **Date:**
* **Campaign:**
* **Channel:**
* **Copy:**
* **Landing:**
* **Tracking:**
* **Human visits (24h / 7d):**
* **Signups:**
* **Trips:**
* **Outcome:**
* **Decision:**
