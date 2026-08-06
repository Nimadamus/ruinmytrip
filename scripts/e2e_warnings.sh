#!/usr/bin/env bash
# End-to-end walk-through of the warning lifecycle against a RUNNING LOCAL instance.
#
# Usage: scripts/e2e_warnings.sh [base-url] [member-email] [admin-email] [password]
#   defaults: http://127.0.0.1:8080 with the e2e_* local accounts
#
# LOCAL ONLY. This script logs in, submits content and moderates it. Pointing it at production
# would create real rows under real accounts, so it refuses any non-localhost base URL.
#
# Two things about the helpers below are load-bearing and were both found the hard way:
#   * csrf() must send AND store cookies (-b -c) so the first call establishes a session, but must
#     NOT follow redirects — following one while writing the jar clobbers the session cookie.
#   * The submitted title is stamped with a per-run tag, because the duplicate guard (correctly)
#     blocks a second identical submission and the moderation step needs a pending row to act on.
set -u
BASE="${1:-http://127.0.0.1:8080}"
MEMBER="${2:-e2e_member@fixture.invalid}"
ADMIN="${3:-e2e_admin@fixture.invalid}"
PASS="${4:-e2e-test-pass-123}"

case "$BASE" in
  http://127.0.0.1*|http://localhost*) ;;
  *) echo "REFUSED: e2e writes real content. Local base URLs only (got $BASE)."; exit 1 ;;
esac

JAR_M=$(mktemp); JAR_A=$(mktemp); JAR_X=$(mktemp)
trap 'rm -f "$JAR_M" "$JAR_A" "$JAR_X"' EXIT
# Unique per RUN, not per second: two runs inside the same second would otherwise submit an
# identical title and be (correctly) blocked by the duplicate guard, which reads as a failure of
# the submit step rather than a success of the guard.
RUNTAG="$(date +%H%M%S)-$$-${RANDOM}"
fail=0

# SEVERAL STEPS HERE ARE RATE LIMITED, BY DESIGN. Running this script repeatedly will eventually
# trip those limits and the affected step will fail — that is the anti-abuse control working, not
# a regression. Current ceilings (see the controllers):
#   warning submission   6 / hour / user       alert subscribe   8 / hour / IP
#   outdated report     10 / hour / user-or-IP watchlist add    30 / hour / user
# To re-run cleanly against the local database:
#   php -c php.local.ini -r "define('BASE_PATH','.');require 'app/loadconfig.php';\
#     \$GLOBALS['config']=rmt_load_config();require 'app/db.php';q_exec('DELETE FROM rate_limits');"

say() { echo; echo "== $1"; }
ck()  { if [ "$2" = "$3" ]; then echo "  OK   $1 ($2)"; else echo "  FAIL $1: got $2 want $3"; fail=$((fail+1)); fi; }
grep_ok() { if echo "$1" | grep -q "$2"; then echo "  OK   $3"; else echo "  FAIL $3"; fail=$((fail+1)); fi; }
csrf() { curl -s -b "$1" -c "$1" "$2" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }

say "sign in as a member"
T=$(csrf "$JAR_M" "$BASE/login")
ck "login redirects" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_M" -c "$JAR_M" -X POST "$BASE/login" \
  --data-urlencode "_csrf=$T" --data-urlencode "email=$MEMBER" --data-urlencode "password=$PASS")" "302"
ck "dashboard reachable" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_M" "$BASE/dashboard")" "200"

say "submit a warning"
FORM=$(curl -s -b "$JAR_M" -c "$JAR_M" "$BASE/warning/new")
T=$(echo "$FORM" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
S=$(echo "$FORM" | grep -o 'name="_submit" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
BODY_TXT="The nightly rate looked fine online but a mandatory cleaning fee was demanded in cash at checkout and appeared nowhere in the booking confirmation."
post_warning() {
  curl -s -b "$JAR_M" -c "$JAR_M" -X POST "$BASE/warning/new" \
    --data-urlencode "_csrf=$1" --data-urlencode "_submit=$2" --data-urlencode "action=submit" \
    --data-urlencode "destination_id=1" --data-urlencode "category=hidden-costs" \
    --data-urlencode "title=E2E $RUNTAG mandatory cleaning fee at checkout" \
    --data-urlencode "body=$BODY_TXT" \
    --data-urlencode "advice=Ask the host for the full total in writing before booking." \
    --data-urlencode "severity=2" --data-urlencode "date_experienced=2026-03" \
    --data-urlencode "cost_impact_usd=85" --data-urlencode "traveler_type=couple" \
    --data-urlencode "attested=1" -o /dev/null -w '%{http_code}'
}
ck "warning POST redirects" "$(post_warning "$T" "$S")" "302"

say "the duplicate guard refuses an identical resubmission"
FORM=$(curl -s -b "$JAR_M" -c "$JAR_M" "$BASE/warning/new")
T=$(echo "$FORM" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
S=$(echo "$FORM" | grep -o 'name="_submit" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
DUP=$(curl -s -b "$JAR_M" -c "$JAR_M" -X POST "$BASE/warning/new" \
  --data-urlencode "_csrf=$T" --data-urlencode "_submit=$S" --data-urlencode "action=submit" \
  --data-urlencode "destination_id=1" --data-urlencode "category=hidden-costs" \
  --data-urlencode "title=E2E $RUNTAG mandatory cleaning fee at checkout" \
  --data-urlencode "body=$BODY_TXT" --data-urlencode "severity=2" \
  --data-urlencode "date_experienced=2026-03" --data-urlencode "attested=1")
grep_ok "$DUP" "already filed a very similar warning" "duplicate refused"

say "validation: the genuine-experience attestation is required"
FORM=$(curl -s -b "$JAR_M" -c "$JAR_M" "$BASE/warning/new")
T=$(echo "$FORM" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
S=$(echo "$FORM" | grep -o 'name="_submit" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
NOATT=$(curl -s -b "$JAR_M" -c "$JAR_M" -X POST "$BASE/warning/new" \
  --data-urlencode "_csrf=$T" --data-urlencode "_submit=$S" --data-urlencode "action=submit" \
  --data-urlencode "destination_id=1" --data-urlencode "category=scams" \
  --data-urlencode "title=E2E $RUNTAG unattested" \
  --data-urlencode "body=This body is long enough to clear the eighty character minimum imposed by the validator for a real report." \
  --data-urlencode "severity=2" --data-urlencode "date_experienced=2026-02")
grep_ok "$NOATT" "own genuine experience" "attestation enforced"

say "CSRF is enforced"
ck "POST with no token is 403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_M" -X POST "$BASE/warning/new" --data 'title=x')" "403"

say "save a trip and follow a destination"
T=$(csrf "$JAR_M" "$BASE/d/paris-france")
ck "watchlist add redirects" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_M" -c "$JAR_M" -X POST "$BASE/watchlist/add" \
  --data-urlencode "_csrf=$T" --data-urlencode "destination_id=1" \
  --data-urlencode "date_from=2026-11-02" --data-urlencode "date_to=2026-11-09" --data-urlencode "return=/dashboard")" "302"
T=$(csrf "$JAR_M" "$BASE/d/paris-france")
ck "follow redirects" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_M" -c "$JAR_M" -X POST "$BASE/destination/follow" \
  --data-urlencode "_csrf=$T" --data-urlencode "destination_id=1" --data-urlencode "return=/d/paris-france")" "302"

say "email alert subscription (logged out, double opt-in)"
T=$(csrf "$JAR_X" "$BASE/alerts")
SUB=$(curl -s -b "$JAR_X" -c "$JAR_X" -X POST "$BASE/alerts/subscribe" \
  --data-urlencode "_csrf=$T" --data-urlencode "email=alerts-e2e@fixture.invalid" \
  --data-urlencode "destination=paris-france" --data-urlencode "frequency=weekly" --data-urlencode "min_severity=2")
grep_ok "$SUB" "Check your email" "double opt-in page shown"

say "report outdated info (no account required)"
T=$(csrf "$JAR_X" "$BASE/alerts")
ck "accepted from an anonymous visitor" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_X" -c "$JAR_X" -X POST "$BASE/outdated" \
  --data-urlencode "_csrf=$T" --data-urlencode "target_type=destination" --data-urlencode "target_id=1" \
  --data-urlencode "return=/d/paris-france")" "302"

say "sign in as admin"
T=$(csrf "$JAR_A" "$BASE/login")
curl -s -o /dev/null -b "$JAR_A" -c "$JAR_A" -X POST "$BASE/login" \
  --data-urlencode "_csrf=$T" --data-urlencode "email=$ADMIN" --data-urlencode "password=$PASS"
for p in /admin /admin/warnings /admin/destinations /admin/destination/1 /admin/pages /admin/page/new \
         /admin/responses /admin/outdated /admin/alerts /admin/affiliates /admin/users \
         /admin/analytics /admin/homepage /admin/reports; do
  ck "admin $p" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_A" "$BASE$p")" "200"
done
ck "a member cannot reach admin" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_M" "$BASE/admin")" "403"

say "moderate the pending warning"
WID=$(curl -s -b "$JAR_A" "$BASE/admin/warnings?status=pending" | grep -o 'admin/warnings/[0-9]*/moderate' | head -1 | grep -o '[0-9]*')
if [ -n "$WID" ]; then
  T=$(csrf "$JAR_A" "$BASE/admin/warnings?status=pending")
  ck "approve redirects (warning #$WID)" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_A" -c "$JAR_A" \
    -X POST "$BASE/admin/warnings/$WID/moderate" \
    --data-urlencode "_csrf=$T" --data-urlencode "action=approve" --data-urlencode "note=E2E approval")" "302"
  ck "approved warning canonicalises publicly" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/w/$WID")" "302"
  T=$(csrf "$JAR_A" "$BASE/admin/warnings?status=pending")
  NONOTE=$(curl -s -L -b "$JAR_A" -c "$JAR_A" -X POST "$BASE/admin/warnings/$WID/moderate" \
    --data-urlencode "_csrf=$T" --data-urlencode "action=reject" --data-urlencode "note=")
  grep_ok "$NONOTE" "Add a short note" "a rejection without a reason is refused"
else
  echo "  FAIL no pending warning found to moderate"; fail=$((fail+1))
fi

say "vote a warning helpful"
OTHER_PATH=$(curl -s "$BASE/warnings" | grep -o '/w/[0-9]*/[a-z0-9-]*' | head -1)
OTHER=$(echo "$OTHER_PATH" | grep -o '[0-9]*' | head -1)
if [ -n "$OTHER" ]; then
  T=$(csrf "$JAR_M" "$BASE$OTHER_PATH")
  ck "vote redirects" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_M" -c "$JAR_M" \
    -X POST "$BASE/warning/$OTHER/helpful" \
    --data-urlencode "_csrf=$T" --data-urlencode "vote=helpful" --data-urlencode "return=$OTHER_PATH")" "302"
fi

echo
if [ "$fail" -eq 0 ]; then echo "E2E: ALL CHECKS PASSED"; exit 0; else echo "E2E: $fail CHECK(S) FAILED"; exit 1; fi
