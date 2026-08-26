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
for p in / /explore /travelers /founding /d/kyoto-japan /guides /reviews /meetups /going /tags \
         /login /register /terms /privacy /guidelines /safety /affiliate \
         /editorial-policy /sitemap.xml /robots.txt \
         /blog /blog/tourist-taxes-2026 /blog/amsterdam-tourist-tax-2026 \
         /blog/kyoto-lodging-tax-2026 /blog/edinburgh-visitor-levy-2026 \
         /blog/anne-frank-house-tickets-2026 /blog/sagrada-familia-tickets-2026 \
         /blog/barcelona-tourist-tax-2026 \
         /b7c4e91a2d8f4e0c9a1b6d5f3e8c2a70.txt /google415ee1e6530e2d0f.html; do check "$p" 200; done
echo "-- editorial layer (expect 200) --"
# These exist on any correctly published instance. They deliberately do NOT reference the demo
# seed, which no longer exists anywhere: a smoke test that only passes against fabricated
# fixtures tells you nothing about production.
check /u/ruinmytrip 200
check /g/kyoto-japan-travel-guide 200
check /d/marrakech-morocco 200
echo "-- negative (expect 404 / auth 302) --"
check /this-page-does-not-exist 404
check /feed 302   # logged out -> /login
check /welcome 302
echo "-- content / DB connectivity --"
if curl -s ${INSECURE} "$BASE/" | grep -q "Honest 2026 travel intel"; then echo "  OK   homepage rendered (DB reachable)"; else echo "  FAIL homepage content / DB"; fail=$((fail+1)); fi
if curl -s ${INSECURE} "$BASE/sitemap.xml" | grep -q "/meetups"; then echo "  FAIL sitemap lists empty /meetups"; fail=$((fail+1)); else echo "  OK   sitemap omits empty /meetups"; fi
if curl -s ${INSECURE} "$BASE/sitemap.xml" | grep -q "<loc>"; then echo "  OK   sitemap has URLs"; else echo "  FAIL sitemap empty"; fail=$((fail+1)); fi
# Editorial content must be labelled wherever it renders. If this ever stops matching, the
# labelling has regressed and the site is passing off its own writing as a traveler's.
if curl -s ${INSECURE} "$BASE/d/kyoto-japan" | grep -q "Official Review"; then echo "  OK   editorial review labelled"; else echo "  FAIL editorial label missing"; fail=$((fail+1)); fi
if curl -s ${INSECURE} "$BASE/d/kyoto-japan" | grep -q "No traveler reviews yet"; then echo "  OK   community rating honest (0 traveler reviews)"; else echo "  NOTE community reviews exist, rating shown"; fi
echo "-- HTTPS cert (informational) --"
curl -sI "$BASE/" 2>/dev/null | grep -i "^HTTP" | head -1

echo
if [ "$fail" -eq 0 ]; then echo "ALL CHECKS PASSED"; exit 0; else echo "$fail CHECK(S) FAILED"; exit 1; fi
