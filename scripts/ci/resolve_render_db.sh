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
# Echoes the resolved id and, under GitHub Actions, exports DB_ID for later steps.
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

DB_ID=$(curl -fsS -H "$AUTH" "$API/services/$SERVICE_ID/env-vars?limit=100" | python3 -c '
import sys, json, re
for item in json.load(sys.stdin):
    var = item.get("envVar", item)
    if var.get("key") == "DATABASE_URL":
        match = re.search(r"@(dpg-[a-z0-9]+(?:-a)?)", var.get("value", ""))
        if not match:
            sys.exit("DATABASE_URL is set but contains no dpg- host")
        print(match.group(1))
        break
else:
    sys.exit("service has no DATABASE_URL env var")
')

# Confirm the resolved instance actually exists before any caller opens a firewall or
# starts a dump against it. Better to fail here, loudly, than halfway through.
curl -fsS -o /dev/null -H "$AUTH" "$API/postgres/$DB_ID"

echo "Resolved production database: $DB_ID (from $SERVICE_NAME DATABASE_URL)"
if [ -n "${GITHUB_ENV:-}" ]; then
  echo "DB_ID=$DB_ID" >> "$GITHUB_ENV"
fi
