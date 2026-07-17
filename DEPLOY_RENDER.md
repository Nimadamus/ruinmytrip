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
Primary system = **automated daily backups via GitHub Actions** — see [`docs/BACKUP_RESTORE.md`](docs/BACKUP_RESTORE.md) for the full procedure.
- Workflow `.github/workflows/db-backup.yml`, daily **07:17 UTC** (+ manual `workflow_dispatch`).
- Each run opens the DB firewall to the runner `/32`, `pg_dump` (custom format), GPG-AES256
  encrypts, **restores into a throwaway container + integrity checks** to prove the dump is usable,
  uploads the encrypted dump as an artifact (**30-day retention** = 30 restore points), then
  **always re-locks** the firewall. Failures email `nj2121@gmail.com` from `noreply@send.ruinmytrip.com`.
- Concurrency-guarded so backups never overlap. DB creds are fetched at runtime via the Render API,
  never committed or stored as a secret.
- **Restore (summary):** download the `rmt-db-backup-<stamp>` artifact →
  `gpg --batch --decrypt --passphrase-fd 0 -o rmt.dump <file>.gpg` →
  `pg_restore --no-owner --no-privileges --exit-on-error --dbname="$CONN" rmt.dump`. Full steps and
  the firewall-open snippet are in `docs/BACKUP_RESTORE.md`.
- Manual one-off dump (temporary `/32` allow-list entry, then re-lock): see the same doc.

## Rollback
- **Code:** Render keeps every deploy. Roll back in the dashboard (Deploys → Rollback) or redeploy a previous commit. Every commit here is reversible.
- **DB:** restore from a Render backup, or re-run `migrate.php` (additive/idempotent).
- **Full undo:** suspend/delete `ruinmytrip-web` and `ruinmytrip-db` — no other Render service is affected.

## DNS (only after the temp URL passes — separate approval)
Point `ruinmytrip.com` + `www` at the Render service; **preserve** existing MX/SPF/DKIM/DMARC and any verification records. Exact record values come from Render's Custom Domain screen and are applied at cutover.
