# RuinMyTrip metrics: what each number means

Source of truth: `app/proof.php`. Served as JSON on `GET /cron/proof?key=CRON_KEY&days=N` (N up to 31)
and as a page on `/admin/proof` (admin and mod only). `scripts/kpi_snapshot.py` writes the daily
scoreboard (`~/rmt_kpi/KPI.md`) from it; `scripts/proof_check.py` reconciles it against `/cron/kpi`.

Every day is a Pacific day (America/Los_Angeles). Stored timestamps are UTC and are moved before
they are bucketed. Our own checks (`utm_content=selfcheck`) are excluded everywhere. House accounts
(`rmt_sc_real_user_sql`: not active, editorial role, or a `team_` username) are excluded from every
product count.

## Browsers: three buckets, one reason each

These buckets are **estimates from behaviour, not identities**. A patient bot that waits and clicks can be counted as a verified human, and a real person who arrives with several others on one link can be counted in a burst. The dashboard reports the reason for every call so it can be checked, and no number on it should be read as an exact count of people.


A browser is the `rmt_v` visitor cookie value. No IP address, user agent or referrer is stored.
Requests whose user agent says bot, spider, crawl, headless, preview and similar are never recorded
at all (`rmt_is_crawler`).

| Bucket | Rule |
|---|---|
| Suspected bot | Checked first, in this order. **webdriver**: the browser reported `navigator.webdriver` (event `automation_detected`). **form_first**: its first recorded event was a form page fetched directly (`review_form_start`, `review_signup_required`, `join_view`, `plan_signup_view`). **burst**: 5 or more new browsers began on the same path (or event) within 60 seconds. **no_cookie**: 2 or more requests with the cookie bit recorded and none returned our cookie. |
| Verified human | No bot signal, and one of: **trusted_input** (event `human_verified`: a trusted tap, click or key, at least 1.5 s after load, no webdriver flag; scroll and synthetic events never count); **submitted_a_form** (any of the action events listed in `RMT_PROOF_ACTION_EVENTS`); **interacted_and_stayed** (the older `human_interaction` plus 10 s or more between first and last event; kept for days before `human_verified` shipped). |
| Unverified | Everything else. One page and nothing after it looks the same from a person skimming and a polite crawler; it is not called either. |

Window totals for the three buckets count distinct browsers across the window; daily rows count a
browser on every day it was seen.

## The daily columns

| Column | Definition | Read from |
|---|---|---|
| verified_humans | Browsers seen that day classified human | contribution_events |
| suspected_bots | Browsers seen that day classified bot | contribution_events |
| unverified | Browsers seen that day with no evidence either way | contribution_events |
| returning_humans | Verified humans also seen on an earlier day in the window | contribution_events |
| search_humans | Verified humans whose first touch channel was search, on their first day | contribution_events.acq_source |
| referral_humans | Verified humans from any channel other than direct or search | contribution_events.acq_source |
| map_users | Verified humans who opened or saved the travel map | map_view, map_saved |
| map_saves | Travel maps saved to an account, any browser | map_saved |
| review_opened | Verified humans who opened the review form | review_form_start |
| review_submitted | Verified humans who pressed Post or Publish | review_submit_attempt |
| review_held | Guest reviews validated and held for the account step, distinct journeys | review_held_for_join |
| reviews_published | Reviews by real members now published, by the day written | reviews |
| signups | Real member accounts created | users |
| confirmed | Of those, accounts whose email is confirmed by now | users.email_verified_at |
| trips | Trips by real members | trips |
| buddy_posts | Travel buddy posts by real members | buddy_posts |
| messages | Messages sent by real members | messages |
| active_members | Distinct real members who wrote anything that day: review, post, comment, trip, buddy post, message, save or follow (likes have no timestamp) | those tables |
| alerts_confirmed | Match alerts whose email link was clicked | match_alerts.confirmed_at |

Rates: signup rate = signups / verified humans; review submit rate = review_submitted / review_opened;
confirm rate = confirmed / signups. A rate with a zero denominator is reported as n/a, never 0.

## Outside the database

Google clicks, impressions and position come from the Search Console API (joined by date in
`scripts/kpi_snapshot.py`, 2 to 3 days behind). Indexed pages come from URL Inspection runs and the
Page indexing report. Revenue is blank until an affiliate network reports a confirmed commission.

## Known limits

* A person who reads one page and never taps is unverified. Real readers are undercounted on
  purpose rather than bots overcounted.
* A real spike (a post that goes viral) can trip the burst rule; the reasons table shows it.
* `human_verified` exists only from the day the branch is deployed. Earlier days rely on the older
  interaction plus dwell rule, which a headless browser that scrolls and waits can still pass.

## Organic Google traffic

Two separate numbers, never added together. **search_humans** is verified humans whose first touch
was a search engine, from our own events. **Google impressions and clicks** come from the Search
Console API by date and are joined into the scoreboard by `scripts/kpi_snapshot.py`; they run two
to three days behind and include people who never reached the page.

## Cost

`/cron/proof` reads events in slices of 20,000 by id and keeps each browser as a few integers.
Measured with `scripts/ci/proof_load_check.php` under the production limit of 256MB: 52,000 events
(production's 31 day volume on 2026-10-11) peak 30MB in 0.7 s; 160,000 events 76MB; 300,000 events
110MB. It stops reading at 400,000 events and says so.
