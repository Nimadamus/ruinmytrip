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
| `BACKUP_PASSPHRASE` | AES256 passphrase for encrypting/decrypting dumps. **Losing this makes every backup unrecoverable.** Stored ONLY in an external password manager — see the incident note below. |
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

## Incident, 2026-08-06 — the passphrase was lost, and why

This document previously said the passphrase was "Stored in `CLAUDE.md` Credentials". That file was
destroyed by the C: NULL-byte corruption on or before 2026-07-24 (29,680 bytes of nulls, with no
readable copy in `.bak` files, logs, Claude file-history, git or repo copies). The passphrase had
been set on 2026-07-17, so it existed in that one location for about a week and was then gone.

### What is actually true (corrected 2026-08-06)

An earlier revision of this section said every artifact must be treated as **UNUSABLE**. That was an
overstatement and is wrong. The precise position is:

**The existing backups ARE decryptable.** The `BACKUP_PASSPHRASE` repo secret still holds the correct
value, and it demonstrably works: workflow run **31100989871** on 2026-08-06 decrypted
`rmt_20260806T122135Z.dump.gpg` inside the runner, restored it with `pg_restore --exit-on-error`, and
reported `integrity checks PASSED` (38 `schema_migrations` rows, 80 `destinations` rows).

**What is missing is a human-held copy of that passphrase.** GitHub secrets are write-only — the value
can be *used* by a workflow but cannot be *read back* by a person. Its only recorded home was
`CLAUDE.md`, which was destroyed. So:

| | Can it decrypt a backup today? |
|---|---|
| A GitHub Actions workflow in this repo | **Yes** — proven by run 31100989871 |
| A person, at a terminal, from the vault | **No** — no known human-held copy |

**The practical risk is therefore narrower than "unusable", and still serious.** Recovery works only
while GitHub Actions is available *and* this repository and its secrets are intact. That is precisely
the scenario least likely to hold in a real disaster — losing the account, the repo, or access to
Actions also loses the only route to your data. It also means recovery cannot be performed by anyone
who lacks push/Actions rights on the repo.

**Nothing is deleted.** All existing artifacts are retained.

**Three rules follow:**

1. **The passphrase never lives in a repo file, a dotfile, or anything on C: alone.** It lives in an
   external password manager. This document records only *where*, never the value.
2. **A backup only a machine can open is only half a backup.** The nightly job's restore test
   decrypts inside the run using the GitHub secret, so it passes whether or not any human can open
   the artifact. It proves the *artifact* is good, not that the *organisation* can recover.
3. **The human-held copy is the thing that must be tested**, not just the artifact. See the drill
   below.

A related failure the same day: `db-backup.yml` hardcoded `DB_ID` as a literal, and the Aug 5 Render
cost-cut recreated the database with a new id. Every API call 404'd and the nightly backup silently
failed on Aug 5 and Aug 6. **When a Render database is recreated, grep the workflows for a hardcoded
`dpg-` id.**

## Quarterly human-led restore drill

The nightly job cannot detect the failure that actually happened here, because it decrypts with a
secret no person can read. Only a human doing the restore proves recovery works. Run this **once a
quarter** and whenever the passphrase is rotated or an operator changes.

**Schedule:** first working week of January, April, July and October.

**Procedure** (about 20 minutes, touches nothing in production):

1. Retrieve `BACKUP_PASSPHRASE` from the external password manager. **If you cannot find it, the
   drill has already failed — stop and rotate.** That is the single most important step.
2. Download the most recent artifact from the latest successful `db-backup` run:
   `gh run download <run-id> -n rmt-db-backup-<stamp> -D ./restore-drill`
3. Start a disposable PostgreSQL matching production's major version:
   `docker run -d --name rmt-drill -e POSTGRES_PASSWORD=drill -e POSTGRES_USER=drill -p 15432:5432 postgres:16`
4. Decrypt locally, entering the passphrase at the Pinentry prompt (never as a command argument):
   `gpg --decrypt -o drill.dump ./restore-drill/rmt_<stamp>.dump.gpg`
5. Restore and check:
   `pg_restore --no-owner --no-privileges --exit-on-error -d "postgresql://drill:drill@127.0.0.1:15432/drill" drill.dump`
6. Confirm the numbers are plausible for the current site — table count, `schema_migrations` rows,
   `destinations` rows — not merely that the restore exited zero.
7. Tear down and erase: `docker rm -f rmt-drill && rm -f drill.dump`
8. Record the date and outcome below.

**Record**

| Date | Artifact | Passphrase retrieved from vault? | Restore result | By |
|---|---|---|---|---|
| _(2026-08-06)_ | `rmt_20260806T122135Z.dump.gpg` | **NO — none held** | Passed in CI only; no human restore | — |

That first row is the failure this drill exists to catch. It should never read "NO" again.

## Rotating the passphrase

Old artifacts stay encrypted under the old passphrase, so keep it until those artifacts expire
(30 days). Set a new `BACKUP_PASSPHRASE` secret with `gh secret set BACKUP_PASSPHRASE < file`
(reads from a file so the value never reaches a command line or shell history), record it in the
external password manager, then securely delete the file. NEVER write it to `CLAUDE.md` or any
other file on C: — that is exactly how the previous passphrase was lost.

## Notes / limits

- Backups are only as current as the last successful daily run; there is no continuous WAL/PITR on
  this plan. Point-in-time recovery would need a paid Render tier or an external WAL archive.
- Artifacts are visible to anyone who can see this (public) repo, which is why they are encrypted;
  the plaintext is never stored anywhere.
