# Acquisition calendar, 15 September to 14 December 2026

The operating calendar. One row per campaign per channel, in the order things have to happen.
Every date here is either verified against a primary source (the event) or chosen from it (the
posting dates). **Nothing speculative is on this calendar.** A cohort whose dates cannot be verified
does not get a row, it gets a line in the notes at the bottom.

**Rule for every row:** post once, wait, read the result in `/admin/funnel` under "Acquisition, now",
write the outcome into `docs/ACQUISITION_EXPERIMENTS.md`, and only then decide whether to post again.
Never the same text in two places.

**Status vocabulary:** `ready` (copy written, link verified, waiting for a person),
`live` (published), `done` (window closed), `blocked` (says why).

---

## September

| Dates | Campaign | Channel | What happens | Status | Tracked campaign |
|---|---|---|---|---|---|
| 15 Sep | oktoberfest | site | Munich page shows the window and the prefilled form, to campaign and search traffic alike | **done, live** | `oktoberfest` |
| 15 Sep | oktoberfest | all | Copy written for Reddit, Facebook, X, Instagram, TikTok, forums | **done** | `oktoberfest` |
| 16 to 18 Sep | oktoberfest | reddit | One post to r/Munich, rules read first | **blocked**, Reddit unreachable from this machine | `oktoberfest` |
| 16 to 18 Sep | oktoberfest | facebook | One post to one Munich or Oktoberfest group | **ready**, needs a personal account | `oktoberfest` |
| 16 to 18 Sep | oktoberfest | x | Two posts, three days apart | **ready**, needs an account | `oktoberfest` |
| 19 Sep to 4 Oct | oktoberfest | all | **The window itself.** Peak posting is 17 to 26 September; after the 28th the useful question changes from "who is going" to "who is still here" | ready | `oktoberfest` |
| 22 Sep | oktoberfest | forums | One post to a travel forum that permits it | ready | `oktoberfest` |
| 29 Sep | seo title test | search | **First honest read of the nine page title experiment.** Not an acquisition action; do not touch the test before this date | scheduled | n/a |

## October

| Dates | Campaign | Channel | What happens | Status | Tracked campaign |
|---|---|---|---|---|---|
| 1 Oct | day-of-the-dead | site | Oaxaca campaign window live. Organic line stays off there while the title test runs | **done, live** | `day-of-the-dead` |
| 5 to 12 Oct | day-of-the-dead | reddit, facebook | Preparation and posting, three to four weeks ahead of travel | ready | `day-of-the-dead` |
| 10 to 20 Oct | web-summit | reddit, linkedin, facebook | Posting window, three to four weeks before the conference | ready | `web-summit` |
| 15 to 25 Oct | yi-peng | reddit, facebook | Posting window. **Copy must not name a specific lantern night** until a Thai calendar is checked | ready | `yi-peng` |
| 20 to 31 Oct | new-year-2027 | reddit, facebook | Posting window for the December to January cohort | ready | `new-year-2027` |
| 31 Oct to 2 Nov | day-of-the-dead | all | **The window itself**, Oaxaca | ready | `day-of-the-dead` |

## November

| Dates | Campaign | Channel | What happens | Status | Tracked campaign |
|---|---|---|---|---|---|
| 1 to 8 Nov | web-summit | all | Peak posting, the week before | ready | `web-summit` |
| 9 to 12 Nov | web-summit | all | **The window itself**, Lisbon | ready | `web-summit` |
| 15 to 22 Nov | yi-peng | all | Peak posting | ready | `yi-peng` |
| 23 to 25 Nov | yi-peng | all | **The window itself**, Chiang Mai | ready | `yi-peng` |
| 20 to 30 Nov | miami-art-week | reddit, facebook, x | Posting window | ready | `miami-art-week` |

## December

| Dates | Campaign | Channel | What happens | Status | Tracked campaign |
|---|---|---|---|---|---|
| 1 to 7 Dec | miami-art-week | all | **The window itself**, Miami | ready | `miami-art-week` |
| 1 to 10 Dec | rio-carnival | reddit, facebook | Preparation and first posts, eight weeks out | copy written | `rio-carnival` |
| 5 to 20 Dec | new-year-2027 | all | Peak posting for the Southeast Asia New Year | ready | `new-year-2027` |
| 27 Dec to 2 Jan | new-year-2027 | all | **The window itself**, Bangkok and Chiang Mai | ready | `new-year-2027` |

## After this calendar ends

Carnival in Rio runs 5 to 13 February 2027 and its posting window opens in early December, which is
why it appears above. Everything after that is written into `docs/ACQUISITION_COHORTS.md` rather than
here, because a calendar that runs further than the verified dates is a guess with a table around it.

## Results

Filled in from `/admin/funnel` and `/cron/funnel`, never from memory. One row per posted item, in
`docs/ACQUISITION_EXPERIMENTS.md`. The four numbers that matter for every row are the same four:
human visits, signups, trips with dates, and whether two trips in that city overlapped.

## What is deliberately not on this calendar

* **Cherry blossom season in Japan.** A forecast, not a date, reissued weekly from January.
* **Any cohort whose organiser has not published 2027 dates.** Guessing at them in copy that goes to
  strangers is how an account gets removed and how a reader stops trusting the site.
* **Paid anything.** No ads, no promoted posts, no bought placements.
