#!/usr/bin/env bash
# Crawl every public route and assert that no response is a 5xx and no response BODY contains a
# PHP diagnostic.
#
# This is deliberately stronger than scraping the dev server's access log. Locally php.local.ini
# sets display_errors=On, so any notice, warning, deprecation or fatal is emitted INTO THE PAGE —
# which means scanning bodies catches errors that a log scrape would miss entirely if the log
# redirect is lost (which it is, for a backgrounded PHP dev server on Windows). It also catches
# errors on pages that still return 200.
#
# Usage: scripts/scan_php_errors.sh [base-url]
set -u
BASE="${1:-http://127.0.0.1:8080}"
bad=0
checked=0

# Any of these appearing in a rendered page means PHP emitted a diagnostic.
NEEDLES='Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught|Stack trace|PDOException'

paths=(
  / /explore /warnings /warning-guides /alerts /search "/search?q=paris" "/search?q=barcelna"
  "/search?q=%22resort+fee%22" "/api/suggest?q=par"
  /warnings/scams /warnings/hidden-costs /warnings/neighborhoods /warnings/transportation
  /warnings/weather /warnings/crowds /warnings/closures /warnings/health-safety
  /warnings/entry-requirements /warnings/accommodation
  /d/paris-france /d/paris-france/warnings /d/paris-france/photos
  "/d/paris-france/warnings?category=scams&severity=3&sort=helpful"
  "/explore?sort=risk" "/explore?sort=warnings" "/explore?sort=covered" "/explore?sort=rating"
  "/explore?sort=popular" "/explore?q=paris" "/explore?category=city"
  /what-can-ruin-a-trip-to-paris /paris-tourist-scams /rome-transportation-mistakes
  /reviews /guides /blog /collections /meetups /going /leaderboard /tags /discover
  /login /register /terms /privacy /guidelines /safety /affiliate /editorial-policy
  /sitemap.xml /feed.xml /healthz /readyz /robots.txt
  /u/ruinmytrip /dashboard /warning/new /feed /admin
  /this-page-does-not-exist /go/not-a-real-slug /w/999999
)

for p in "${paths[@]}"; do
  checked=$((checked+1))
  code=$(curl -s -o /tmp/_scan_body -w '%{http_code}' "$BASE$p")
  if [ "${code:0:1}" = "5" ]; then
    echo "  5xx  $code  $p"; bad=$((bad+1)); continue
  fi
  if grep -qE "$NEEDLES" /tmp/_scan_body; then
    echo "  PHP DIAGNOSTIC IN BODY  ($code)  $p"
    grep -oE "$NEEDLES.{0,120}" /tmp/_scan_body | head -2 | sed 's/^/       /'
    bad=$((bad+1))
  fi
done
rm -f /tmp/_scan_body

echo
echo "  crawled $checked routes on $BASE"
if [ "$bad" -eq 0 ]; then echo "  CLEAN: no 5xx, no PHP diagnostics in any response body"; exit 0; fi
echo "  $bad problem(s)"; exit 1
