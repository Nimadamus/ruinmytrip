# Travel map activation: three staged changes, 2026-10-12

Status: staging only. Nothing here is deployed. Each release needs Nima's approval in chat.

## What the numbers said (production, read 2026-10-12 04:55 PDT)

Source: `/admin/proof` and `/admin/funnel`, snapshot `~/tools/rmt_ops/db_s12_social_baseline.json`.
Window: 31 days (proof), 27 day clean window for human visits.

| Measure | Value |
|---|---|
| Verified human visits | 39 |
| Suspected bots kept out | 15,125 |
| Real member signups | 1 (proof shows 2; the second is the marked QA member) |
| Returning humans | 4 |
| Reviews published | 0 |
| City page views (destination_page_view) | 2,340 |
| City page interactions (follow, ask, react, comment) | 1 |
| People who used the travel map (map_users) | 0 |
| Maps saved (map_saves) | 0 |
| Map share clicks | 0 |
| Visits from a shared link | 0 |

Readers land on city pages (Oaxaca, Lisbon, Bangkok, Chiang Mai, Munich, Miami lead), and nearly
none of them do anything. The travel map is the one thing a stranger can make with no account, so
the three changes connect city page, map, account and sharing.

## The three changes, in release order

| Order | Commit | Branch | What it does | Event to watch |
|---|---|---|---|---|
| 1 | 3412f93 | feat/dest-been-map-20261012 | Signed out, a city page's "I've been" opens `/map?c=<country>` already coloured in, no account (was "Join to mark been") | `cta_click` detail `dest_been_map` |
| 2 | 846fc3a | feat/map-keep-join-20261012 | "Keep it on a free profile" keeps the map through joining (before: 0 countries saved after joining) | `map_saved` source `map_join` |
| 3 | f2f0f21 | feat/map-shared-view-20261012 | A link from "Share my map" opens read only for the friend, with "Make your own travel map" (before: the friend saw the sharer's countries as their own) | `map_view` source `share`, then `cta_click` detail `map_make` |

The branches are stacked (3 contains 2 contains 1), so they ship as **one merge of
feat/map-shared-view-20261012 into main**, which auto deploys. Rollback: revert that merge.

Separate and independent: **aa55355** (branch fix/a11y-tracking-20261012): dark mode tab bar,
badges and place actions, home alert contrast, map region role, and QA members (`+rmtqa`) kept out
of proof and the scorecard.

## Success measures after release

| Step | Baseline | Target (first 30 days after release and Campaign 1) |
|---|---|---|
| City page human landings that press "I've been" | 0 of 2,340 views | 2% of human city landings |
| Maps kept by people who joined from "Keep it" | 0 (broken) | 60% of keep presses that finish joining |
| Map share clicks | 0 | counted per human map user |
| Shared link visits that press "Make your own" | 0 | 20% |
| Members with a saved map | 0 | every new member who came from the map |

Read them from `/admin/proof` (map_users, map_saves) and `/admin/funnel` (acquisition rows, cta).
Our own checks (utm_content=selfcheck, the qa campaigns, bot agents) stay out of every number.

## SEO invariants (checked on staging)

No new routes. `/map` and every `/map?c=...` canonical to `/map`, robots index, follow, unchanged.
The city page link is `rel="nofollow"`. City page canonicals unchanged. Sitemap untouched.

## Evidence

Scratchpad `evidence_f2f0f21/` (runs after the commit, 06:06 to 06:15 PDT): gate 141 green;
map_shared_view, map_keep_join, dest_been_map and member_journey e2e logs, current design and v2,
desktop and phone, all 0 failed; axe 0 serious or critical on the map pages; 8 screenshots.
