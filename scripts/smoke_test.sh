#!/usr/bin/env bash
# RuinMyTrip post-deployment smoke test.
# Usage: scripts/smoke_test.sh https://ruinmytrip.com   [-k]
# Pass -k as 2nd arg to ignore TLS cert mismatch during pre-DNS hosts-file testing.
#
# The checks below deliberately do NOT reference demo/seed data, which no longer exists anywhere:
# a smoke test that only passes against fabricated fixtures tells you nothing about production.
set -u
BASE="${1:-http://localhost:8080}"
INSECURE="${2:-}"
CURL="curl -s ${INSECURE} -o /dev/null -w %{http_code}"
fail=0

check() { # path expected
  code=$($CURL "$BASE$1")
  if [ "$code" = "$2" ]; then echo "  OK   $2  $1"; else echo "  FAIL $code (want $2)  $1"; fail=$((fail+1)); fi
}
has() { # path needle label
  if curl -s ${INSECURE} "$BASE$1" | grep -q "$2"; then echo "  OK   $3"; else echo "  FAIL $3"; fail=$((fail+1)); fi
}

echo "Smoke testing $BASE"

echo "-- the risk product (expect 200) --"
for p in / /explore /warnings /warning-guides /alerts /search; do check "$p" 200; done
echo "-- all ten warning categories --"
for c in scams hidden-costs neighborhoods transportation weather crowds closures health-safety \
         entry-requirements accommodation; do check "/warnings/$c" 200; done

echo "-- the twenty launch destinations --"
for s in paris-france london-uk rome-italy barcelona-spain new-york-city-usa las-vegas-usa \
         los-angeles-usa cancun-mexico mexico-city-mexico tokyo-japan bangkok-thailand \
         istanbul-turkiye dubai-uae amsterdam-netherlands athens-greece lisbon-portugal \
         miami-usa orlando-usa honolulu-usa san-francisco-usa; do check "/d/$s" 200; done
check /d/paris-france/warnings 200

echo "-- preserved routes from the pre-2026 social site (must never break) --"
for p in /guides /reviews /meetups /going /tags /collections /blog /discover /leaderboard \
         /login /register /terms /privacy /guidelines /safety /affiliate /editorial-policy \
         /sitemap.xml /robots.txt /feed.xml /healthz /readyz; do check "$p" 200; done
check /u/ruinmytrip 200
# Use destinations guaranteed by a MIGRATION, not by the demo seeder. marrakech-morocco and
# kyoto-japan exist only in database/seed.php, so they are present on the live site (which
# seeded on first boot) but absent from any database rebuilt from migrations alone — which
# made these checks pass in production and fail on a rebuilt environment. See docs/ROUTES.md.
check /d/paris-france 200

echo "-- negative / auth (expect 404 or 302) --"
check /this-page-does-not-exist 404
check /feed 302          # logged out -> /login
check /dashboard 302     # logged out -> /login
check /warning/new 302   # logged out -> /login
check /go/not-a-real-affiliate-slug 404

echo "-- content and DB connectivity --"
has / "Know What Could Ruin Your Trip"            "homepage headline rendered (DB reachable)"
has / "What can ruin a trip?"                     "the ten categories on the homepage"
has /sitemap.xml "<loc>"                          "sitemap has URLs"
has /sitemap.xml "/warnings"                      "sitemap includes the warnings section"
has /d/paris-france "Overall trip risk"           "destination risk report rendering"
has /d/paris-france "Last reviewed"               "reviewed date shown (freshness signal)"
has /d/paris-france "FAQPage"                     "FAQ structured data emitted"
has /d/paris-france "TouristDestination"          "destination structured data emitted"
has /warnings "Travel warnings"                   "warnings index rendering"
has /api/suggest?q=par "destination"              "autocomplete endpoint responding"

echo "-- trust labelling (a regression here means we are misrepresenting content) --"
# Editorial content must be labelled wherever it renders, and traveler warnings must carry their
# verification state. If either stops matching, the site is passing something off as something else.
has /d/paris-france "Official Review"             "editorial review labelled"
has /warning-guides "Researched, dated, sourced"  "guide index states what the guides are"

echo "-- no accidental noindex anywhere (hard rule) --"
for p in / /explore /warnings /d/paris-france /warning-guides /alerts; do
  if curl -s ${INSECURE} "$BASE$p" | grep -qi 'name="robots"[^>]*noindex'; then
    echo "  FAIL noindex found on $p"; fail=$((fail+1))
  fi
done
echo "  OK   no noindex on the sampled pages"

echo "-- HTTPS cert (informational) --"
curl -sI "$BASE/" 2>/dev/null | grep -i "^HTTP" | head -1

echo
if [ "$fail" -eq 0 ]; then echo "ALL CHECKS PASSED"; exit 0; else echo "$fail CHECK(S) FAILED"; exit 1; fi
