#!/usr/bin/env bash
# RuinMyTrip post-deployment smoke test.
# Usage: scripts/smoke_test.sh https://ruinmytrip.com   [-k]
# Pass -k as 2nd arg to ignore TLS cert mismatch during pre-DNS hosts-file testing.
set -u
BASE="${1:-http://localhost:8080}"
INSECURE="${2:-}"
CURL="curl -s ${INSECURE} -o /dev/null -w %{http_code}"
fail=0

check() { # path expected
  code=$($CURL "$BASE$1")
  if [ "$code" = "$2" ]; then echo "  OK   $2  $1"; else echo "  FAIL $code (want $2)  $1"; fail=$((fail+1)); fi
}

echo "Smoke testing $BASE"
echo "-- public routes (expect 200) --"
for p in / /explore /d/kyoto-japan /guides /reviews /meetups /going \
         /login /register /terms /privacy /guidelines /safety /affiliate \
         /u/maya_wanders /sitemap.xml /robots.txt; do check "$p" 200; done
echo "-- detail pages (expect 200) --"
check /trip/1/three-quiet-mornings-in-kyoto 200
check /g/oaxaca-food-itinerary 200
check /meetup/1 200
echo "-- negative (expect 404 / auth 302) --"
check /this-page-does-not-exist 404
check /feed 302   # logged out -> /login
echo "-- content / DB connectivity --"
if curl -s ${INSECURE} "$BASE/" | grep -q "Trending now"; then echo "  OK   homepage rendered (DB reachable)"; else echo "  FAIL homepage content / DB"; fail=$((fail+1)); fi
if curl -s ${INSECURE} "$BASE/sitemap.xml" | grep -q "<loc>"; then echo "  OK   sitemap has URLs"; else echo "  FAIL sitemap empty"; fail=$((fail+1)); fi
echo "-- HTTPS cert (informational) --"
curl -sI "$BASE/" 2>/dev/null | grep -i "^HTTP" | head -1

echo
if [ "$fail" -eq 0 ]; then echo "ALL CHECKS PASSED"; exit 0; else echo "$fail CHECK(S) FAILED"; exit 1; fi
