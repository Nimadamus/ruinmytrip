#!/usr/bin/env bash
# Resolve the production Postgres instance id from the web service's own DATABASE_URL.
#
# Why this exists: the id used to be hardcoded, separately, in db-backup.yml and
# weekly-digest.yml. The 2026-08-05 plan migration created a new Postgres instance and
# deleted the old one, so every Render API call in those workflows 404'd. The nightly
# backup broke on Aug 5/6/7, and the weekly digest would have broken on Aug 10. The id
# was then corrected in one workflow but not the other, which is exactly the failure
# this script removes: there is now one place to resolve it, and nothing to update by
# hand when an instance is replaced.
#
# The database the running app is configured to talk to is the only definition of
# "production" that cannot go stale, so that is what we read.
#
# Requires: RENDER_API_KEY. Optional: RENDER_SERVICE_NAME (default: ruinmytrip-web).
# Echoes the resolved id and database name and, under GitHub Actions, exports DB_ID and
# DB_NAME for later steps.
set -euo pipefail

: "${RENDER_API_KEY:?RENDER_API_KEY is required}"
SERVICE_NAME="${RENDER_SERVICE_NAME:-ruinmytrip-web}"
API="https://api.render.com/v1"
AUTH="Authorization: Bearer $RENDER_API_KEY"

SERVICE_ID=$(curl -fsS -H "$AUTH" "$API/services?name=$SERVICE_NAME&limit=1" | python3 -c '
import sys, json
data = json.load(sys.stdin)
if not data:
    sys.exit("no Render service named " + sys.argv[1])
print(data[0]["service"]["id"])
' "$SERVICE_NAME")

RESOLVED=$(curl -fsS -H "$AUTH" "$API/services/$SERVICE_ID/env-vars?limit=100" | python3 -c '
import sys, json, re, urllib.parse
for item in json.load(sys.stdin):
    var = item.get("envVar", item)
    if var.get("key") == "DATABASE_URL":
        url = var.get("value", "")
        match = re.search(r"@(dpg-[a-z0-9]+(?:-a)?)", url)
        if not match:
            sys.exit("DATABASE_URL is set but contains no dpg- host")
        # The instance can host several databases (ruinmytrip was moved onto the
        # betlegend instance on 2026-09-03), so the database NAME has to come from
        # this URL too. Rendering it from the instance default would dump the wrong
        # database -- which is exactly what happened on 2026-09-04.
        name = urllib.parse.urlsplit(url).path.lstrip("/")
        if not name:
            sys.exit("DATABASE_URL has no database name in its path")
        print(match.group(1))
        print(name)
        break
else:
    sys.exit("service has no DATABASE_URL env var")
')
DB_ID=$(printf '%s
' "$RESOLVED" | sed -n 1p)
DB_NAME=$(printf '%s
' "$RESOLVED" | sed -n 2p)

# Confirm the resolved instance actually exists before any caller opens a firewall or
# starts a dump against it. Better to fail here, loudly, than halfway through.
curl -fsS -o /dev/null -H "$AUTH" "$API/postgres/$DB_ID"

echo "Resolved production database: $DB_ID database=$DB_NAME (from $SERVICE_NAME DATABASE_URL)"
if [ -n "${GITHUB_ENV:-}" ]; then
  echo "DB_ID=$DB_ID" >> "$GITHUB_ENV"
  echo "DB_NAME=$DB_NAME" >> "$GITHUB_ENV"
fi
