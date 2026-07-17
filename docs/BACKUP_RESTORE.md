# RuinMyTrip — Database Backups & Restore

Automated, scheduled backups of the production PostgreSQL database. Runs on GitHub Actions with
no manual trigger and never writes to production.

## What runs, when, where

- **Workflow:** `.github/workflows/db-backup.yml` (job `backup`).
- **Schedule:** daily at **07:17 UTC** (`cron: '17 7 * * *'`), plus manual `workflow_dispatch`.
- **Overlap protection:** `concurrency: db-backup`, `cancel-in-progress: false` — a second run
  queues behind the first; two backups can never run at once.
- **Destination:** encrypted dump stored as a GitHub Actions **artifact**
  `rmt-db-backup-<UTC timestamp>`, **retention 30 days** (30 daily restore points; GitHub prunes
  older automatically).
- **Encryption:** GPG symmetric **AES256**. Passphrase is the `BACKUP_PASSPHRASE` repo secret,
  passed to gpg over stdin (never on the command line, never logged). An artifact on its own is
  useless without the passphrase.
- **Firewall:** the DB is normally internal-only (`ipAllowList: []`). Each run opens it to the
  runner's `/32` for the dump, then an `if: always()` step re-locks it — even on failure/cancel.
- **Alerting:** any failure emails `nj2121@gmail.com` from `noreply@send.ruinmytrip.com` (Resend).
- **Self-verification:** every run restores its own dump into a throwaway `postgres:16` service
  container and asserts the expected tables + migrations + catalog rows exist. A backup that does
  not restore cleanly fails the run (and alerts).

## Secrets (GitHub → repo → Settings → Secrets → Actions)

| Secret | Purpose |
|---|---|
| `RENDER_API_KEY` | Toggle the DB firewall + fetch the connection string at runtime (so DB creds are never stored as a secret or committed). |
| `BACKUP_PASSPHRASE` | AES256 passphrase for encrypting/decrypting dumps. **Losing this makes every backup unrecoverable.** Stored in `CLAUDE.md` Credentials. |
| `RESEND_API_KEY` | Send the failure alert email. |

The production DB id (`dpg-d9co0937uimc73enjljg-a`) and alert address are non-secret and live in
the workflow `env:`.

## Restore procedure (production incident)

You need: the `BACKUP_PASSPHRASE`, the target database connection string, `gpg`, and
`postgresql-client-16` (`pg_restore`).

1. **Get the encrypted dump.** GitHub → repo → Actions → `db-backup` → pick the run for the day
   you want → download the `rmt-db-backup-<stamp>` artifact. Unzip it to get `rmt_<stamp>.dump.gpg`.

2. **Decrypt:**
   ```bash
   printf '%s' "$BACKUP_PASSPHRASE" | gpg --batch --yes --decrypt --passphrase-fd 0 \
     -o rmt.dump rmt_<stamp>.dump.gpg
   ```

3. **Restore.** *Never restore straight onto a live DB you cannot lose.* Prefer a fresh empty
   database, verify it, then cut over.
   ```bash
   # into a NEW empty database:
   pg_restore --no-owner --no-privileges --exit-on-error \
     --dbname="postgresql://USER:PASS@HOST:5432/NEW_DB?sslmode=require" rmt.dump
   ```
   To restore into an existing database, add `--clean --if-exists` (drops objects first) — only do
   this against a database you intend to overwrite.

4. **Verify** (same checks the workflow runs):
   ```bash
   psql "$CONN" -c "\dt"
   psql "$CONN" -c "SELECT count(*) FROM schema_migrations;"   # expect the full migration set
   psql "$CONN" -c "SELECT count(*) FROM destinations;"        # expect >= 8
   ```

## Opening the firewall for a manual restore/dump

The DB is internal-only. To connect from outside for a one-off, open a single `/32` then re-lock:
```bash
IP=$(curl -s https://api.ipify.org)
curl -s -X PATCH -H "Authorization: Bearer $RENDER_API_KEY" -H "Content-Type: application/json" \
  -d "{\"ipAllowList\":[{\"cidrBlock\":\"$IP/32\",\"description\":\"manual\"}]}" \
  "https://api.render.com/v1/postgres/dpg-d9co0937uimc73enjljg-a"
# ... do the work ...
curl -s -X PATCH -H "Authorization: Bearer $RENDER_API_KEY" -H "Content-Type: application/json" \
  -d '{"ipAllowList":[]}' "https://api.render.com/v1/postgres/dpg-d9co0937uimc73enjljg-a"
```

## Rotating the passphrase

Old artifacts stay encrypted under the old passphrase, so keep it until those artifacts expire
(30 days). Set a new `BACKUP_PASSPHRASE` secret and update `CLAUDE.md`; new runs use the new one.

## Notes / limits

- Backups are only as current as the last successful daily run; there is no continuous WAL/PITR on
  this plan. Point-in-time recovery would need a paid Render tier or an external WAL archive.
- Artifacts are visible to anyone who can see this (public) repo, which is why they are encrypted;
  the plaintext is never stored anywhere.
