#!/usr/bin/env bash
# One city's worth of places: a deliberate mix across the kinds a traveler looks for.
#
# Sequential and unhurried on purpose. Overpass is a free service run by volunteers; the pauses are
# the difference between being a heavy user of it and being the reason it gets locked down.
#
#   SITE=https://ruinmytrip.com KEY=… scripts/city_kit.sh paris-france
set -u
SITE=${SITE:-https://ruinmytrip.com}
KEY=${KEY:?KEY is required}
PHP=${PHP:-php}
DRY=${DRY:-}
CITY=${1:?a destination slug is required}

KIT=(
  "restaurant:restaurant:25" "restaurant:cafe:12" "restaurant:bar:10" "restaurant:pub:6"
  "attraction:museum:12" "attraction:park:8" "attraction:viewpoint:6" "attraction:monument:6"
  "attraction:marketplace:4" "attraction:mall:3"
  "experience:theatre:5" "experience:nightclub:5" "experience:stadium:3"
  "hotel:hotel:12" "hotel:hostel:4"
)

echo "### $CITY"
fails=0
for spec in "${KIT[@]}"; do
  IFS=: read -r ty kind n <<< "$spec"
  out=$($PHP scripts/push_places.php --site="$SITE" --key="$KEY" --city="$CITY" \
        --type="$ty" --osm="$kind" --limit="$n" ${DRY:+--dry} 2>&1)
  line=$(echo "$out" | grep -E '^offered=' || true)
  if [ -z "$line" ]; then
    echo "  $kind: FAILED"
    echo "$out" | sed 's/^/     /' | tail -3
    fails=$((fails+1))
  else
    echo "  $kind: $line"
  fi
  sleep 7
done
echo "### $CITY done, $fails kind(s) failed"
