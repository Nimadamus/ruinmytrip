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

## Safety & privacy (non-negotiable)
- Meetups are **public, optional, community** connections — never dating/hookups.
- No precise real-time location. Visibility is **destination-level + date-range** only, opt-in.
- Reporting, blocking, moderation, age gate (16+ to use, 18+ to host meetups), community standards.
