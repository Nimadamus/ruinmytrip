#!/usr/bin/env bash
# Trust-labelling audit: does an UNVERIFIED traveler report ever render as though it were verified?
#
# This is the single most important safety property on the site. A published warning is an
# allegation until a moderator corroborates it, and publication alone must never confer the
# appearance of verification — including on a warning that names a real business.
#
# Checks every surface a warning renders on: the permalink, the destination page, the destination
# warning list, the global list, a category page, search results and the homepage.
#
# Usage: scripts/verify_trust_labels.sh [base-url] [unverified-slug] [verified-slug]
set -u
BASE="${1:-http://127.0.0.1:8080}"
UNV="${2:-lbl-unverified}"
VER="${3:-lbl-verified}"
fail=0

ck()   { if [ "$2" = "$3" ]; then echo "  OK   $1"; else echo "  FAIL $1 (got '$2' want '$3')"; fail=$((fail+1)); fi; }
# Count occurrences of a needle in a page.
cnt()  { curl -s "$BASE$1" | grep -c "$2" || true; }
# Count occurrences in the SUBJECT warning's own region only — everything before the "More ...
# warnings in ..." related block. Without this scoping the related cards (correctly labelled with
# their own verification state) are counted against the warning being examined, and a page showing
# one verified neighbour reads as a false positive.
cnt_subject() { curl -s "$BASE$1" | sed '/<h2>More /,$d' | grep -c "$2" || true; }
gt0_subject() { if [ "$(cnt_subject "$1" "$2")" -gt 0 ]; then echo "  OK   $3"; else echo "  FAIL $3"; fail=$((fail+1)); fi; }
eq0_subject() { if [ "$(cnt_subject "$1" "$2")" -eq 0 ]; then echo "  OK   $3"; else echo "  FAIL $3"; fail=$((fail+1)); fi; }
gt0()  { if [ "$(cnt "$1" "$2")" -gt 0 ]; then echo "  OK   $3"; else echo "  FAIL $3"; fail=$((fail+1)); fi; }
eq0()  { if [ "$(cnt "$1" "$2")" -eq 0 ]; then echo "  OK   $3"; else echo "  FAIL $3"; fail=$((fail+1)); fi; }

UNV_ID=$(curl -s "$BASE/warnings" | grep -o "/w/[0-9]*/$UNV" | head -1 | grep -o '[0-9]*' | head -1)
VER_ID=$(curl -s "$BASE/warnings" | grep -o "/w/[0-9]*/$VER" | head -1 | grep -o '[0-9]*' | head -1)
echo "unverified warning id=$UNV_ID  verified warning id=$VER_ID"
if [ -z "$UNV_ID" ] || [ -z "$VER_ID" ]; then echo "FAIL: fixtures not found"; exit 1; fi

echo
echo "-- the unverified warning's own page --"
gt0 "/w/$UNV_ID/$UNV" 'trust trust-unverified'   "carries the Unverified chip"
eq0_subject "/w/$UNV_ID/$UNV" 'trust trust-verified' "carries NO Verified chip"
gt0 "/w/$UNV_ID/$UNV" 'Unverified traveler report' "states in words what it is"
gt0 "/w/$UNV_ID/$UNV" 'not independently confirmed' "says it is not independently confirmed"

echo
echo "-- the verified warning's own page (the label must still be earned, and distinct) --"
gt0 "/w/$VER_ID/$VER" 'trust trust-verified'     "carries the Verified chip"
gt0 "/w/$VER_ID/$VER" 'Verified report'          "explains what verification means"
gt0 "/w/$VER_ID/$VER" 'remains one traveler'     "still qualifies it as one person's experience"

echo
echo "-- every list surface labels verification state --"
for path in "/warnings" "/d/paris-france" "/d/paris-france/warnings" "/warnings/accommodation" "/search?q=Hotel+Example" "/"; do
  n_cards=$(cnt "$path" 'class="warn-card')
  n_trust=$(cnt "$path" 'class="trust trust-')
  if [ "$n_cards" -eq 0 ]; then echo "  --   $path (no warning cards rendered)"; continue; fi
  if [ "$n_trust" -ge "$n_cards" ]; then
    echo "  OK   $path — $n_cards card(s), $n_trust trust label(s): every card labelled"
  else
    echo "  FAIL $path — $n_cards card(s) but only $n_trust trust label(s)"; fail=$((fail+1))
  fi
done

echo
echo "-- naming a business does not upgrade an allegation --"
gt0 "/w/$UNV_ID/$UNV" 'Hotel Example'                    "the business is named"
gt0 "/w/$UNV_ID/$UNV" "the traveler's own estimate\|Business involved" "named under a neutral label"
eq0_subject "/w/$UNV_ID/$UNV" 'trust trust-verified'     "still not marked verified"
gt0 "/w/$UNV_ID/respond" 'Respond to a warning'          "the business has a response route"
gt0 "/w/$UNV_ID/respond" 'do not delete a traveler'      "the response policy is stated to the business"

echo
echo "-- the site-wide legacy 'Verified visit' badge stays off (no system stands behind it) --"
eq0 "/d/paris-france" 'class="verified"'                 "no verified-visit badge on the destination page"

echo
if [ "$fail" -eq 0 ]; then echo "TRUST LABELLING: ALL CHECKS PASSED"; exit 0; else echo "TRUST LABELLING: $fail CHECK(S) FAILED"; exit 1; fi
