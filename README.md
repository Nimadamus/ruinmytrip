# RuinMyTrip.com

A bold, trustworthy travel social network — traveler profiles, trip stories, verified reviews of
destinations/hotels/restaurants/nightlife/attractions/tours, travel guides & itineraries, a follow
system, "Who's going" destination discovery, and **optional, safety-first** public travel meetups.

The name is playful; the product is premium, modern, adventurous, and safe. Not a dating/hookup site,
not a joke page, not a generic blog.

## Stack
- **PHP 8** + **PDO** (MySQL in production, SQLite for local dev/test) — native to Namecheap shared cPanel.
- No framework lock-in. Server-rendered, mobile-first, SEO-first (unique indexable URLs, sitemap,
  canonical/OG/JSON-LD, breadcrumbs).
- Zero build step: deploy the repo to the cPanel docroot.

## Local dev
```bash
# from repo root
php -S localhost:8080 -t public public/router.php
# first run seeds a local SQLite DB automatically (see app/config.php APP_ENV=local)
```
Visit http://localhost:8080

The auto-seed fabricates members and reviews so the UI has something to render. That is fine for
layout work and useless for judging how the live site actually behaves. To preview what production
looks like, build a database with the real destinations and **zero** invented content:

```bash
php scripts/dev_preview_db.php                       # 8 destinations, 0 users, 0 reviews
RMT_SQLITE=$PWD/database/preview.sqlite php scripts/publish_editorial.php --apply
RMT_SQLITE=$PWD/database/preview.sqlite RMT_APP_URL=http://127.0.0.1:8099 \
  php -S 127.0.0.1:8099 -t public public/router.php
```

## Tests
```bash
php tests/csrf_test.php          # CSRF token states
php tests/editorial_test.php     # editorial labelling + community-rating isolation
bash scripts/smoke_test.sh https://ruinmytrip.com
```

## Config
Copy `app/config.sample.php` -> `app/config.php` and set DB + secrets. `config.php` is gitignored.

## Deploy (later, after approval)
See `ARCHITECTURE.md` -> Deployment. DNS is **not** changed until the build is approved.

## Operations
- **Deployment:** `DEPLOY_RENDER.md` (production = Render, Docker + PostgreSQL).
- **Auto-deploy:** pushes to `main` deploy automatically via `.github/workflows/render-deploy.yml`.
- **Database backups & restore:** `docs/BACKUP_RESTORE.md`. Automated daily encrypted backups run
  via `.github/workflows/db-backup.yml` (07:17 UTC), self-verified by an isolated restore + integrity
  check each run, 30-day retention, email alert on failure. Restore steps are in that doc.

## Editorial content (non-negotiable)
The site launched with no traveler reviews because it had no travelers. It does **not** solve that
with invented users. Instead it publishes researched editorial content under one official account
(`users.role = 'editorial'`), and three rules are enforced in code, not by convention:

1. **Authorship is the label.** Editorial = the author's role. Every render path asks
   `app/editorial.php`, so unlabelled editorial content cannot exist and no per-row flag can drift
   away from who wrote the words.
2. **Editorial ratings never enter a community average.** `rmt_community_avg()` and the
   explore/home counts filter by role. A destination page can show our 5/5 next to
   "No traveler reviews yet" and both are literally true.
3. **No claimed visits.** Editorial reviews carry no `visited_on` and never the verified badge, and
   a disclosure sentence appears wherever one is read in full.

Publishing:
```bash
python scripts/build_editorial_content.py <research_dir>   # research -> database/editorial/content.json
php scripts/publish_editorial.php --check                  # validate only
php scripts/publish_editorial.php                          # dry run
php scripts/publish_editorial.php --apply                  # write (idempotent, safe against prod)
```
`publish_editorial.php` is the opposite of `database/seed.php`: the seeder is hard-blocked in
production because it fabricates people, this one is allowed there because it does not. Facts are
checked against operator and government sources at time of writing; where a current price cannot be
sourced it is described qualitatively rather than guessed. Photographs are real, freely licensed,
imported into the `media` table (Commons allow-lists thumbnail widths and discourages hotlinking)
and credited with licence and source link. Policy page: `/editorial-policy`.

## Safety & privacy (non-negotiable)
- Meetups are **public, optional, community** connections — never dating/hookups.
- No precise real-time location. Visibility is **destination-level + date-range** only, opt-in.
- Reporting, blocking, moderation, age gate (16+ to use, 18+ to host meetups), community standards.
