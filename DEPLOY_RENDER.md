# RuinMyTrip — Render Deployment

Production runs on **Render** (Docker web service + managed PostgreSQL). No secrets live in this repo — all
configuration is injected via Render environment variables (see `app/loadconfig.php`).

## Services (Render, region: oregon)
| Kind | Name | Notes |
|------|------|-------|
| PostgreSQL | `ruinmytrip-db` | Plan Basic-256MB (paid). Persistent, managed, daily backups. |
| Web service | `ruinmytrip-web` | Docker (`Dockerfile`), PHP 8.3 + Apache, docroot `public/`. |

## Environment variables (set on the web service)
- `APP_ENV=production`
- `APP_URL` — the public base URL (temp `*.onrender.com`, then `https://ruinmytrip.com` at cutover)
- `DATABASE_URL` — Render Postgres **internal** connection string (wired from `ruinmytrip-db`)
- `SECURITY_SALT` — 64 random hex chars (generated)
- `SEED_DEMO` — `1` to seed demo content on an empty DB; set `0` to stop seeding

## How it boots
`Dockerfile` builds PHP-Apache with `pdo_pgsql`. `docker/entrypoint.sh`:
1. Runs `php database/migrate.php` — idempotent: creates tables `IF NOT EXISTS`, seeds only when the DB is empty (non-fatal, so a DB blip won't crash the web tier).
2. Binds Apache to Render's `$PORT` and serves `public/`.

Health check path: **`/healthz`** (returns `ok db=ok|down`).

## Schema / migrations
- Canonical schema: `database/schema.pgsql.sql` (Postgres). Also `schema.mysql.sql` / `schema.sqlite.sql` for other targets.
- `database/migrate.php` applies schema + optional seed. Runs automatically on every deploy/restart (idempotent).

## Local development
```
php -c php.local.ini -S localhost:8080 -t public public/router.php   # SQLite, auto-seeded
```

## Backups & restore
- Render Postgres (paid plan) includes automated daily backups + point-in-time recovery from the dashboard.
- Manual dump (with a temporary IP allow-list entry): `pg_dump "$EXTERNAL_DATABASE_URL" > backup.sql`.
- Restore: `psql "$EXTERNAL_DATABASE_URL" < backup.sql`.

## Rollback
- **Code:** Render keeps every deploy. Roll back in the dashboard (Deploys → Rollback) or redeploy a previous commit. Every commit here is reversible.
- **DB:** restore from a Render backup, or re-run `migrate.php` (additive/idempotent).
- **Full undo:** suspend/delete `ruinmytrip-web` and `ruinmytrip-db` — no other Render service is affected.

## DNS (only after the temp URL passes — separate approval)
Point `ruinmytrip.com` + `www` at the Render service; **preserve** existing MX/SPF/DKIM/DMARC and any verification records. Exact record values come from Render's Custom Domain screen and are applied at cutover.
