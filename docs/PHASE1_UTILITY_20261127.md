# Phase 1: RuinMyTrip as a single player utility (2026-11-27)

Staging branch `feat/phase1-utility-1127` only. Nothing deployed, nothing merged to main, no production
migration run. Built from the owner's plan of 2026-11-27.

## What changed

**Messaging and home (views/home_v2.php)**
- H1 "Don't let hidden fees, scams, and tourist traps ruin your trip." and the new lede.
- Search placeholder "Where are you going? e.g., Las Vegas, Rome, Cancun...".
- Five destination pills: the five cities with the most published warnings (real counts).
- Value strip of three: Spot Hidden Costs / Avoid Local Scams / Build a Trip Checklist.
- "Know before you go" grid of six city cards: photo, name, real warning count, one preview warning
  (tag, title, cost badge only when the source carries a figure), link to the full checklist.
- The Santorini hero photo and overlay are unchanged.
- SEO preserve mode (default, 2026-11-27 release review): every existing home section and internal link
  stays, plus a "More destinations:" chip row. The community sections are hidden only when
  `RMT_P1_TRIM_LINKS=1` is set (not approved).

**Navigation (views/layout/nav_v2.php, header.php, footer.php, tabbar_v2.php)**
- Header: logo, Explore Traps, Report a Trap (high contrast button, also on phones), Log In / Sign Up.
- By default the header, sheet menu and footer keep every existing link (SEO preserve mode). Only with
  `RMT_P1_TRIM_LINKS=1`: Travel Talk, Who's going, Meetups, Collections (and the header "Travel buddies" / "Meet travelers" link) are hidden from the header,
  sheet menu and footer unless switched back on. Their URLs still work. The footer also hides Travel and
  Cruise buddies and Travelers (`going`), Upcoming events (`events`), Communities (`communities`) and Top
  Reviewers (`reviewers`); its "Community / Meetup Safety" column becomes "Get started / Safety" and the
  tagline drops "optional meetups" (all back with `meetups` or the flags above).
- The mobile tab bar "+" goes to Report a Trap (for that city on a city page).

**City page (views/_p1_warnings.php, views/destination.php, views/destination_v2.php)**
- "What could catch you out in {City}" with tabs: All Warnings / Hidden Fees & Taxes / Scams & Ripoffs /
  Overhyped & Crowded / Local Rules & Logistics, each with a real count and an honest empty note.
- Each card: impact tag (Costly Surprise / Scam Alert / Time Waster, and Local Rule for the fourth tab), cost badge only
  when the source text has the figure, title, two or three sentences, source link,
  Helpful and Saved Me (no account), Save to Trip Checklist.
- Each tab shows its first 8 cards and a "Show all N warnings" button (every card stays in the HTML;
  without JavaScript all show; a `#w-...` link opens the full list).
- "Save Full City Checklist" at the top (hero button on the v2 page).
- Warnings come only from data the site already publishes: the tourist tax table, the 2027 tax
  changes, the before you book list, the official scam list and editor published traveler stories.
- SEO dedupe (default): where `/warnings/{city}` is indexable (at least `RMT_HUB_MIN_WARNINGS` scams), the
  scam and before you book cards show a short line and link to `/warnings/{city}` instead of repeating
  its text. The full text on city pages needs `RMT_P1_FULL_SCAMS=1` (not approved).
- Bottom of every city page: "Have you been to {City}? Did any surprise fee or trap catch you off
  guard?" and "Yes, tell the next traveler" opens the three step form.

**Conversion (app/phase1.php, views/_p1_join_dialog.php, views/phase1_join.php, public/assets/js/phase1.js)**
- A guest clicking Save gets a dialog with the exact copy: "Create a free account to save this warning
  to your trip checklist and get notified if new traps are reported for {City}." The full city button
  uses "...to save this city checklist to your trip and get notified...".
- The save waits in the session; signing up (or logging in) returns to `/checklist/claim`, which
  writes it to `checklist_saves` and lands on `/saved#checklist` ("Your trip checklist").
- Without JavaScript the same prompt is a page at `/checklist/join`.

**Contribution loop (views/_p1_report*.php, views/phase1_report_page.php)**
- Three steps: category (Hidden Fee, Scam, Overhyped, Local Rule), what happened (20 to 1000
  characters), approximate extra cost (optional). No account needed.
- The report goes into the existing editor queue (feedback kind `trip_story`, topic `warnings:{city}`,
  status `pending`), the same path trip stories use. Nothing shows until an editor publishes it at
  `/admin/stories`; then it appears as a traveler card on that city.
- After sending, a guest is asked to "Make this report yours" (join or log in). A report never grants
  the Founding Traveler badge (that still needs a published review; tested). Joining puts
  their name on the report (`feedback.reported_by`). Honeypot and a per address rate limit apply.

**Guest votes**
- `POST /d/{city}/warning-vote`, stored in `warning_votes`, unique per city, warning, voter and kind.
  The voter is a random httponly cookie stored only as a salted hash (members by user id).
  Rate limit 60 per address per hour. The page shows totals; the pressed state is kept in the
  browser because city pages are page cached for guests.

## Flags

| Flag | Effect |
|---|---|
| `RMT_PHASE1` | On unless set to `0`, `off`, `false` or `no`. Off renders the site exactly as before. |
| `RMT_SOCIAL_ON=talk,going,meetups,collections,events,communities,reviewers` (or `all`) | Show those social links again everywhere. |
| `RMT_P1_TRIM_LINKS=1` | Not approved. Hides the social links in header, footer, sheet and home. Off = every existing link kept. |
| `RMT_P1_FULL_SCAMS=1` | Not approved. Full scam and before you book text on city pages that already have an indexable `/warnings/{city}`. |
| `RMT_SOCIAL_CITY_ON=lisbon-portugal=talk\|going,paris-france=all` | Show them again on one city's page only. |

`going` covers Who's going and Travel buddies links. Note: Phase 1 is ON by default once this code is
deployed; set `RMT_PHASE1=0` on the host first if the deploy should not switch it on.

## Migrations

`database/migrations/135_phase1_votes_checklist.{sqlite,pgsql}.sql`: tables `warning_votes` and
`checklist_saves` (both new, nothing altered). Account deletion removes a member's rows from both
(app/account_delete.php, inventory updated). Applied to a COPY of the dev database only.

## Tests

- `tests/phase1_test.php`: 39 PASS. Covers real data and honest cost badges, guest vote dedupe,
  stored counts and rate limit, the held save surviving signup onto the dashboard checklist, full city
  save, the guest report held in the editor queue and credited after joining, and every flag combination.
- Full suite (`tests/*_test.php`): 194 of 194 pass (pre push gate). `occasions_test` ("a written guide
  is indexable") had a date time bomb (Yi Peng ended 2026-11-26), fixed in the test only.
- `place_v2_test` needs port 8191 free; it fails only when another test server is already running.

## Screenshots

Headless Chromium at 1440 and 390 wide, local server on 127.0.0.1:8099 with a COPY of the dev
database, `RMT_DESIGN_V2=all`. Saved in `C:\Users\BL\rmt_research\phase1_1127\` (script `shots.py`):
home, destination (full page, warnings block, scams tab, bottom prompt), signup prompt open,
dashboard after the save (`/saved#checklist`), the three report steps and `/report-a-trap`.
Horizontal overflow measured 0 px on every page at both widths. The save prompt shows the exact copy,
and the signup lands on `/saved#checklist` with the warning on it.

## Open questions for the owner

1. Notifications: built (see New trap alerts below). Needs a schedule approved.
2. Founding Traveler: decided, an editor published report does NOT grant it (test in phase1_test).
3. Duplicate content: not implemented by default; behind `RMT_P1_FULL_SCAMS` pending SEO approval.
4. Fewer internal links: not implemented by default; behind `RMT_P1_TRIM_LINKS` pending SEO approval.
   Before/after link counts are in `docs/PHASE1_RELEASE_20261127.md`.
5. `/report-a-trap` is `noindex,follow` and already covered by the existing `Disallow: /report` in
   robots.txt. Nothing in robots, canonicals, sitemaps or titles was changed.
6. The full city variant of the signup copy differs from the exact copy (it says "this city
   checklist"). Keep, or use the exact copy for both?

## Approvals needed before anything goes live

- Deploy of this branch, and setting the flags on the host.
- Running migrations 135 and 136 on production.
- Turning on the trap-alerts schedule (`.github/workflows/trap-alerts.yml`, manual only for now).
- The SEO adjacent items above (fewer nav, footer and home links; duplicate warnings content).

## Other notes

- Old links to `/d/{city}#scams` (blog, ruined, place and review pages) land on the warnings block and
  open the Scams tab.
- On phones the header button reads "Report" (full "Report a Trap" from 481 px up); Log In / Sign Up
  sit in the menu and tab bar below 900 px, as before.
- The home "Explore a destination" card stays; "Warn the next traveler" (replaced by Report a Trap) and
  "Find travel companions" are hidden in Phase 1.
- The safety page stays linked in the footer as "Safety". On phones the Amsterdam city page is 15,909 px
  tall (was 22,640); the warnings block is 3,887 px with the first 8 cards.

## New trap alerts (2026-11-27)
The signup promise "get notified if new traps are reported" is real: `app/trap_alerts.php` + `scripts/send_trap_alerts.php [--dry-run]`.
When an editor publishes a traveler story on `warnings:{city}`, every member with that city on their checklist gets one email per city per run (mutes, opt out, unverified and the daily cap all apply via `rmt_notify_email_direct`). Migration 136 `trap_alerts_sent` stops repeats. Each (member, story) pair is claimed in `trap_alerts_sent` BEFORE the send and the claim is removed if the send fails, so two overlapping runs cannot both email the same story. Workflow `.github/workflows/trap-alerts.yml`: two crons (15:00 and 16:00 UTC) plus a gate step that continues only when it is 08 in America/Los_Angeles, so exactly one run per day at 8 AM Pacific across daylight saving. The whole job is skipped unless the repo variable `TRAP_ALERTS_AUTO` is `true` (not set: automatic sending is OFF). A day with no new published reports sends nothing (tested).
