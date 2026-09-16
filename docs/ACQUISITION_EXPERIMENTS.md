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
