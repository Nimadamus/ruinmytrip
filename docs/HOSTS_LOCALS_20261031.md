# Hosts & Locals (2026-10-31)

## Audit: what already existed and was reused

| Need | Existing piece reused |
|---|---|
| One account, one profile | users + profiles (photo, bio, home city, languages, interests, travel style) |
| Meet a Local | profiles.open_to_meeting + home_destination_id, local_connects requests (app/buddies.php) |
| Travel Buddy | buddy_posts, trips, trip_connects, the unified buddy search (rmt_buddy_search_run) |
| Messaging | conversations/messages, opened only by an accepted request (rmt_message_allowed), rate limits, new-thread cap, block, report |
| Adults only | age gate (app/age_gate.php, can_host_meetups) |
| Trust facts | app/trust.php (account age, email confirmed, public counts) |
| Moderation | reports, moderation_log, /admin/moderation, suspensions |
| Group activities | trips with members, trip activities, and (new 10/31) trip chat |

Nothing was rebuilt. New pieces only where nothing existed.

## Added

* Migration 117: `profiles.local_offers`, `profiles.local_availability`, `host_profiles` (no address column by design), `member_references`.
* `app/hosts_locals.php`: roles, validation, discovery, references, admin.
* Pages: `/hosts-locals`, `/hosts-locals/destinations`, `/hosts-locals/destinations/{city}`, `/hosts-locals/how-it-works`, `/hosts-locals/safety`, `/hosts-locals/become-a-host`, `/hosts-locals/me`, `/u/{user}/references`, `/admin/hosts`.
* Nav: "Hosts & Locals" in the More menu and the phone sheet. Profile: host chip, references chip.
* Account deletion removes host profile and every reference by or about the member.

## Roles

* Traveler: every member.
* Local: open_to_meeting with a home city, plus offers and availability. Never accommodation.
* Host: an application. Hidden until a moderator approves it. Pausable and withdrawable. Editing an approved profile sends it back to review.

## Safety gates

* `rmt_stays_enabled()` returns false in code (not a setting). No stay request or booking path exists.
* Badges reflect real checks only: "Email confirmed"; "Identity checked" only when `host_profiles.id_verified_at` is set, which nothing sets yet. Every host card says "Identity not checked yet".
* Neighbourhood field refuses numbers and street words. No address, street, postcode or coordinates are stored.
* References require an accepted connection at least 24 hours old (local connect, trip connect, accepted buddy interest, or shared trip membership); no self references; one per person per kind; 10 new per day; reportable as "dispute"; moderators can hide.
* Hosting and meeting are 18+ and need a confirmed email.

## Before overnight stays can open (needs Nima's approval)

1. Identity verification vendor. Options: Stripe Identity (about $1.50 per check), Persona, Veriff. This is a money decision.
2. Stay request flow with host accept or decline, a stay-specific report path and an incident runbook.
3. Legal review: local short term letting and guest registration rules, consumer protection terms, privacy notice update for ID data, insurance position.
4. Moderator coverage for safety reports.

## SEO

* New pages carry self canonicals and unique copy. A city page is `noindex` until a real member is on it, so no thin city pages are indexed.
* The new pages are NOT in any sitemap and no existing sitemap, canonical or robots rule was changed. Adding `/hosts-locals`, `/hosts-locals/how-it-works`, `/hosts-locals/safety`, `/hosts-locals/become-a-host` and populated city pages to the sitemap needs approval.

## Recruiting the first locals and hosts (plan, nothing sent)

1. Existing members with a home city: an in-site prompt on /buddies/mine and the profile edit page to switch on "local".
2. City pages for the eight featured cities: ask on the site's own talk threads for those cities.
3. Outreach drafts for expat and newcomer groups go into docs/OUTREACH_DRAFTS and need Nima's approval per post, same rules as before.
4. Invite a friend already exists (/invite); the hub links profile sharing.

## Tests

* `tests/hosts_locals_test.php` (logic, visitor pages), `tests/account_delete_test.php` (deletion), gate suite.
* `scripts/ci/hosts_locals_e2e.py BASE`: full journey in a real browser on phone and desktop against a local server.
