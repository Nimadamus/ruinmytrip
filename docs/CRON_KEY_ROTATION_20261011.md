# CRON_KEY rotation (prepared, not run)

Why: until 2026-10-11 04:02 PDT the scheduled scripts sent the key as `?key=` in the URL, so it is
in weeks of Render access logs. Treat it as known to anyone who has read those logs. Since cde078c
every caller sends it in the `X-Cron-Key` header, which the access log does not record, so a new key
will not leak the same way.

## Where the key lives (mapped 2026-10-11 from code and 7 days of Render logs)

| Holder | Reads it how | Changes in the rotation |
|---|---|---|
| Render env var `CRON_KEY` on ruinmytrip-web | the app, every `/cron/*` route (`rmt_cron_key_given()`) | set to the new value |
| `~/CREDENTIALS.md` on this PC | `scripts/batch_report.py`, `scripts/social_from_site.py` | old value replaced in place |
| Task "RMT KPI Daily" (`scripts/kpi_snapshot.py`) | from the Render API at run time | nothing to change |
| Task "RMT Social Daily" (`social_daily.py` -> `social_from_site.py`) | CREDENTIALS.md | covered by the line above |

Callers seen in the logs (7 days): `/cron/proof`, `/cron/kpi`, `/cron/funnel`, `/cron/social`,
`/cron/places`, all from this PC. No Render cron job, GitHub workflow or outside service uses it.

## The tool

`~/tools/rmt_ops/rotate_cron_key.py` (outside the repository; never prints a key, only a 4
character fingerprint of its sha256).

- Dry run (no flag): reads the current value from Render, counts where it appears in
  CREDENTIALS.md, checks it works, names the live commit. Run 2026-10-11 09:00 PDT: fingerprint
  `85f3`, 1 occurrence in CREDENTIALS.md, `/cron/proof` with it answers 200, live commit cde078c.
- `--apply`: new 48 character key generated in memory; Render env var set; a deploy of the same
  live commit so the instance restarts with it; waits until live; replaces the old value in
  CREDENTIALS.md; then proves new key -> 200 and old key -> 404 on `/cron/proof`. If the deploy does
  not go live, it puts the old value back on Render and leaves CREDENTIALS.md alone.

## Run order (needs the owner's go: it is a production config change and a redeploy)

1. Nothing else deploying; main equals the live commit.
2. `python ~/tools/rmt_ops/rotate_cron_key.py --apply` (about 3 minutes, mostly the deploy).
3. Run each scheduled job once by hand and check its log: `RMT KPI Daily` and `RMT Social Daily`
   (Task Scheduler "Run"; both only read and write local files, neither posts anything), then `python scripts/proof_check.py`.
4. Render logs for the next hour: no `/cron/*` 404 from our own callers, no `key=` in any URL.
5. SEO invariant snapshot (`prod_check.py`) unchanged: the deploy restarts the same commit.

Rollback: the old value is not kept anywhere by the tool on purpose. If a caller breaks, the fix is
to give that caller the new value (read from Render), not to go back to a leaked key.
