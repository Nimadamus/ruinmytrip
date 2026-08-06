# RuinMyTrip.com

**Know what could ruin your trip before you book it.**

A travel-warning and trip-risk platform. RuinMyTrip collects the practical problems that wreck real
trips — tourist scams, hidden fees, bad neighbourhood choices, transport mistakes, closures, crowding,
seasonal risks and entry-requirement surprises — as **researched destination risk reports** plus
**first-hand traveler warnings** that go through moderation before they are published.

It is deliberately not a generic travel social network and not a TripAdvisor clone. The older
community features (trip stories, reviews, guides, collections, meetups, who's going, top reviewers)
still exist and are still linked, but they are secondary to the risk product — see
`docs/ROUTES.md`, which records exactly what changed and confirms that **no public route was
deleted or renamed** in the 2026 repositioning.

## The two content types, and why they are separate

| | Risk report | Traveler warning |
|---|---|---|
| Written by | The RuinMyTrip editorial team | A member, about their own trip |
| Source | Published research, cited on the page | First-hand experience |
| Published | Immediately, once reviewed | Only after a moderator approves it |
| Labelled | "Checked facts" / "Our guidance" / "Time-sensitive" | Unverified / Verified / Disputed |
| Carries | Sources + a "last reviewed" date | Date experienced + date submitted + severity |

Both label themselves on every render path. A traveler warning is an **allegation** until a moderator
corroborates it, and it says so — which is what makes it safe to let members name a business, and why
a named business can file a response that publishes at the same prominence.

## Stack
- **PHP 8** + **PDO**, no framework. PostgreSQL in production (Render, Docker), SQLite for local dev.
- Server-rendered, mobile-first, SEO-first: unique indexable URLs, sitemap, canonical/OG/Twitter/JSON-LD,
  breadcrumbs. **Never `noindex`** — unpublished content is protected by access control and a 404.
- Zero build step. 24KB of CSS, 4KB of JS, no third-party scripts, no tracking tags.

## Local dev
```bash
php -c php.local.ini -S 127.0.0.1:8080 -t public public/router.php
```
Visit http://127.0.0.1:8080 (`app/config.php` sets `app_url` to match — the port matters, because
redirects are absolute).

Local fixture data (synthetic, production-guarded, purgeable):
```bash
php scripts/dev_fixtures.php              # bulk users/trips/reviews
php scripts/dev_warning_fixtures.php      # ~80 warnings across the first destinations
php scripts/dev_warning_fixtures.php --purge
```

## Tests
```bash
for t in tests/*_test.php; do php -c php.local.ini "$t"; done   # 17 suites
bash scripts/smoke_test.sh http://127.0.0.1:8080                # routes, SEO, trust labelling
bash scripts/e2e_warnings.sh                                    # full warning lifecycle (local only)
```
`e2e_warnings.sh` signs in, submits, moderates and votes. Several steps are rate limited by design;
re-running it repeatedly will trip those limits — see the header comment in the script.

## Publishing risk reports
```bash
php scripts/publish_risk_content.php --check   # validate, write nothing
php scripts/publish_risk_content.php --apply   # write, single transaction
php scripts/publish_risk_content.php --apply --only=paris-france
```
Reads `database/editorial/risk_content.json` and writes `destination_risk_sections`,
`destination_faqs` and `seo_landing_pages`. It **never creates a destination row** — base rows belong
in a numbered migration, so the schema history shows when a destination appeared. It refuses to write
if any section is under 120 characters or any published guide page is under 600, because a page that
restates a database row is worse than no page.

## Alerts
```bash
php scripts/send_alerts.php --dry-run   # print exactly what would go to whom
php scripts/send_alerts.php             # send
```
Four independent brakes stop this becoming spam: per-trip frequency, a per-trip severity floor, a
unique index on `alert_deliveries` that makes double-sending physically impossible, and a frequency
window checked before any batch is built. A recipient with nothing new gets nothing — there is no
"here is your update: nothing happened" email anywhere in the codebase.

## Editorial content (non-negotiable)
The site launched with no traveler reviews because it had no travelers, and it does **not** solve that
with invented users. Researched content is published under one official account
(`users.role = 'editorial'`), with three rules enforced in code rather than by convention:

1. **Authorship is the label.** Editorial = the author's role. Every render path asks
   `app/editorial.php`, so unlabelled editorial content cannot exist.
2. **Editorial ratings never enter a community average.** A destination page can show our assessment
   next to "No traveler reviews yet" and both are literally true.
3. **No claimed visits.** Editorial content carries no `visited_on` and never a verified badge.

The same principle governs risk reports: **no traveler experience is ever invented**. Risk sections
state researched facts with sources; anything first-person is a `warnings` row written by a real member.

Editorial prose goes through `rmt_rich()` (`app/richtext.php`), which escapes everything and then
rebuilds structure from a tiny closed markup — so there is no path from stored text to executable HTML
even for an admin-authored page.

## Monetization
Built, disclosed, and switched off. `affiliate_links.active` defaults to 0, there are no seeded links,
and every outbound partner link renders through one component that always emits the disclosure and
always sets `rel="sponsored nofollow noopener"`. Warnings, risk reports and FAQs are never gated.

## Operations
- **Deployment:** `DEPLOY_RENDER.md` (Render, Docker + PostgreSQL). Pushes to `main` auto-deploy via
  `.github/workflows/render-deploy.yml`.
- **Routes and route changes:** `docs/ROUTES.md`.
- **Backups & restore:** `docs/BACKUP_RESTORE.md`. Daily encrypted backups, self-verified by an
  isolated restore each run, 30-day retention.
- **Migrations** are forward-only and additive; `database/migrations/`, tracked in `schema_migrations`.

## Safety & privacy (non-negotiable)
- Meetups are **public, optional, community** connections — never dating/hookups.
- No precise real-time location. Visibility is destination-level + date-range only, opt-in.
- Reporting, blocking, moderation, age gate (16+ to use, 18+ to host meetups), community standards.
- Analytics are first-party only. The visitor key is a salted hash that rotates every 24 hours; no raw
  IP, no user agent, no third-party tag.
