#!/usr/bin/env python3
"""Propose place attributes from OpenStreetMap. Proposes only; writes nothing.

Why this exists
---------------
Migration 047 gave a place room for an address, coordinates, a phone number, a website and opening
hours, and every one of those is NULL for every place in production. Filling them by hand is
fifteen minutes each. Filling them by guessing is not an option: a wrong address on a page that
looks authoritative is worse than a page with a gap in it.

OpenStreetMap is the one source that both covers these fields at scale and may legally be
redistributed: ODbL, attribution required, no scraping of anybody's review corpus involved. This
script asks Nominatim about one place at a time and writes down what it got, with the OSM object it
came from, so a human or the applier can decide field by field.

What it will NOT do
-------------------
  * invent a value. A field OSM does not return is absent from the proposal, not blank-filled.
  * create a place. It only proposes attributes for slugs that already exist; matching an external
    spelling to a new row is how a directory ends up with two Bellagios.
  * pretend to understand an opening_hours string it cannot fully parse. OSM's syntax has
    conditionals, holidays, seasons and comments; anything outside the plain "Mo-Fr 09:00-17:00;
    Sa off" subset is reported as unparsed and dropped rather than half-read.
  * write to any database. It emits JSON; scripts/apply_place_enrichment.php does the writing, with
    its own dry run and its own field-by-field diff.

Usage
-----
    python scripts/enrich_places.py --candidates database/enrichment/candidates.json \\
                                    --out database/enrichment/proposal.json

A candidates file is a list of objects:

    {"slug": "warsaw-rising-museum", "name": "Warsaw Rising Museum", "city": "Warsaw",
     "country": "Poland", "type": "attraction", "timezone": "Europe/Warsaw"}

`timezone` is optional and is passed through untouched: Nominatim does not return one, and deriving
it from a country would be wrong for every country with more than one. Set it by hand or leave it
out.

Nominatim's usage policy allows one request per second from a real, identifiable User-Agent. Both
are respected below; do not remove either.
"""
from __future__ import annotations

import argparse
import io
import json
import os
import re
import sys
import time
import unicodedata
import urllib.parse
import urllib.request
from difflib import SequenceMatcher

# Place names are not ASCII and a Windows console is not UTF-8. Printing 'Vítězná' must not
# kill a batch halfway through, leaving half a proposal file and no clear reason why.
if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

NOMINATIM = 'https://nominatim.openstreetmap.org/search'
UA = 'RuinMyTrip/1.0 place enrichment (nj2121@gmail.com)'
ATTRIBUTION = 'Data (c) OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright'
DAYS = {'mo': 0, 'tu': 1, 'we': 2, 'th': 3, 'fr': 4, 'sa': 5, 'su': 6}


def fold(s: str) -> str:
    """Lowercase, strip accents and punctuation, for comparing two spellings of one name."""
    s = unicodedata.normalize('NFKD', s or '')
    s = ''.join(c for c in s if not unicodedata.combining(c))
    return re.sub(r'[^a-z0-9]+', ' ', s.lower()).strip()


def similarity(a: str, b: str) -> float:
    """How alike two names are, ignoring word order.

    Word order matters to a reader and not to identity: "Kyubey Ginza" and "Ginza Kyubey" are one
    restaurant, and a straight sequence ratio scores them 0.50. Take the better of the sequence
    ratio and the same ratio over sorted tokens, so a reordering costs nothing and a genuinely
    different name still scores low.
    """
    fa, fb = fold(a), fold(b)
    if not fa or not fb:
        return 0.0
    direct = SequenceMatcher(None, fa, fb).ratio()
    sorted_a = ' '.join(sorted(fa.split()))
    sorted_b = ' '.join(sorted(fb.split()))
    return max(direct, SequenceMatcher(None, sorted_a, sorted_b).ratio())


def query(name: str, city: str, country: str) -> list[dict]:
    params = urllib.parse.urlencode({
        'q': '%s, %s, %s' % (name, city, country),
        'format': 'jsonv2', 'limit': '5',
        'addressdetails': '1', 'extratags': '1', 'namedetails': '1',
        # Ask in English. Without this Nominatim answers in the local language and every geography
        # check fails on an exonym: Athens is returned as Athina, Copenhagen as Kobenhavn, Vienna
        # as Wien, Kyoto in Japanese. Those are the same cities, and refusing them was the check
        # comparing an English name to a local one and calling the difference a discrepancy.
        # Street names still come back local where there is no English form, which is correct: a
        # traveler looking for the door needs the name on the door.
        'accept-language': 'en',
    })
    req = urllib.request.Request(NOMINATIM + '?' + params, headers={'User-Agent': UA})
    with urllib.request.urlopen(req, timeout=40) as r:
        return json.loads(r.read().decode('utf-8'))


# Countries that write the house number before the street. Everywhere else it goes after. This is
# an explicit list rather than a guess because "West 23rd Street 222" is not an American address
# and "222 Karlova" is not a Czech one, and Nominatim hands back the parts without an opinion.
NUMBER_FIRST = {'us', 'ca', 'gb', 'ie', 'au', 'nz', 'fr', 'in', 'za', 'my', 'sg', 'hk',
                'il', 'th', 'ph', 'nl', 'be', 'lu'}


def street_line(road: str, house, country_code) -> str:
    """One street line from Nominatim's parts, ordered the way that country writes it."""
    if not house:
        return road
    cc = (country_code or '').lower()
    return ('%s %s' % (house, road)) if cc in NUMBER_FIRST else ('%s %s' % (road, house))


def parse_opening_hours(raw: str):
    """Parse the plain subset of OSM opening_hours. Returns (intervals, unparsed_reason).

    Handles:  Mo-Fr 09:00-17:00 ; Sa 10:00-14:00 ; Su off
    Refuses:  24/7, PH, "sunrise", month/season ranges, week numbers, comments in quotes,
              anything with a conditional. A rule we cannot read in full is a rule we do not
              store: half of a venue's hours is worse than none of them, because it looks complete.
    """
    if not raw:
        return [], 'no value'
    text = raw.strip()
    lowered = text.lower()
    for bad in ('24/7', 'ph', 'sh', 'sunrise', 'sunset', 'week ', 'easter', '"', 'open', '||'):
        if bad in lowered:
            return [], 'unsupported syntax: %s' % bad.strip()
    if re.search(r'\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\b', lowered):
        return [], 'seasonal rule'

    intervals = []
    for rule in text.split(';'):
        rule = rule.strip()
        if not rule:
            continue
        # A rule with no day prefix applies to every day; that is what OSM's syntax means by a
        # bare time range, not something we are inferring.
        bare = re.match(r'^(?:\d{1,2}:\d{2}-\d{1,2}:\d{2})(?:\s*,\s*\d{1,2}:\d{2}-\d{1,2}:\d{2})*$', rule)
        if bare:
            day_part, time_part = 'Mo-Su', rule.lower()
        else:
            m = re.match(r'^([A-Za-z,\- ]+?)\s+(off|closed|(?:\d{1,2}:\d{2}-\d{1,2}:\d{2})'
                         r'(?:\s*,\s*\d{1,2}:\d{2}-\d{1,2}:\d{2})*)$', rule)
            if not m:
                return [], 'rule not understood: %s' % rule
            day_part, time_part = m.group(1).strip(), m.group(2).strip().lower()

        days = []
        for chunk in day_part.split(','):
            chunk = chunk.strip().lower()
            rng = re.match(r'^([a-z]{2})\s*-\s*([a-z]{2})$', chunk)
            if rng:
                a, b = DAYS.get(rng.group(1)), DAYS.get(rng.group(2))
                if a is None or b is None:
                    return [], 'unknown day: %s' % chunk
                days += list(range(a, b + 1)) if a <= b else list(range(a, 7)) + list(range(0, b + 1))
            elif chunk in DAYS:
                days.append(DAYS[chunk])
            else:
                return [], 'unknown day: %s' % chunk

        if time_part in ('off', 'closed'):
            for d in days:
                intervals.append({'day_of_week': d, 'closed': True})
            continue
        for span in time_part.split(','):
            o, c = [t.strip() for t in span.strip().split('-')]
            o, c = ('0' + o if len(o) == 4 else o), ('0' + c if len(c) == 4 else c)
            if c == '24:00':
                # Midnight as a closing time. "00:00-24:00" is OSM for open all day, which an
                # interval with equal ends cannot express; 23:59 is accurate to the minute and is
                # what a venue open around the clock is shown as everywhere else. Any other span
                # ending at 24:00 simply closes at midnight.
                c = '23:59' if o == '00:00' else '00:00'
            for d in days:
                intervals.append({'day_of_week': d, 'opens': o, 'closes': c})
    return intervals, None


# What OSM calls the kinds of thing we hold. A match whose OSM class cannot be the kind of place we
# are enriching is wrong however well the name scores -- "Pestana Palace Lisboa Tesla Destination
# Charger" is a near-perfect string match for a hotel and is a car charger.
OSM_TYPES_FOR = {
    'hotel': {'hotel', 'hostel', 'guest_house', 'motel', 'resort', 'apartment', 'chalet',
              'alpine_hut', 'camp_site', 'caravan_site', 'building', 'yes', 'house', 'apartments'},
    'restaurant': {'restaurant', 'cafe', 'bar', 'pub', 'fast_food', 'bakery', 'biergarten',
                   'ice_cream', 'food_court', 'nightclub', 'building', 'yes'},
    'attraction': {'museum', 'attraction', 'artwork', 'gallery', 'viewpoint', 'monument',
                   'memorial', 'castle', 'ruins', 'church', 'cathedral', 'chapel', 'mosque',
                   'synagogue', 'temple', 'place_of_worship', 'park', 'garden', 'zoo',
                   'aquarium', 'theme_park', 'beach', 'theatre', 'arts_centre', 'library',
                   'archaeological_site', 'historic', 'building', 'yes', 'tower', 'bridge',
                   'square', 'marketplace', 'city_gate', 'fort', 'palace', 'manor'},
    'experience': {'attraction', 'tour', 'travel_agency', 'boat_rental', 'ferry_terminal',
                   'water_park', 'spa', 'sauna', 'building', 'yes'},
}

# OSM classes that are never any kind of place we hold, whatever they are called.
NEVER = {'charging_station', 'parking', 'bus_stop', 'bus_station', 'atm', 'bicycle_parking',
         'toilets', 'waste_basket', 'fuel', 'post_box', 'bench', 'street_lamp', 'traffic_signals'}


def type_verdict(osm_type_tag: str, our_type: str):
    """(verdict, reason) for whether an OSM result can be the kind of place we are enriching.

    Three outcomes, because two were not enough. NEVER is a hard veto: a charging station is not a
    hotel however well the name reads, and that is the case this check exists for. An unexpected
    class is a different thing entirely -- OSM tags a patisserie shop=pastry and a pasta bar
    shop=pasta, and both are places you eat. Refusing those was the check being wrong, not strict.
    So an unrecognised class costs confidence and is recorded, and a name that matches strongly
    still survives it.
    """
    t = (osm_type_tag or '').lower()
    if not t:
        return 'ok', None                       # nothing to judge on; the score decides
    if t in NEVER:
        return 'never', 'candidate is a %s, which is never a place we list' % t
    allowed = OSM_TYPES_FOR.get(our_type)
    if allowed and t not in allowed:
        return 'unexpected', 'OSM tags this %s, which is not a usual %s' % (t, our_type)
    return 'ok', None


def propose(cand: dict) -> dict:
    # `search_name` lets a human improve the QUERY when our display name is not what the place is
    # called on a map ("Hassler Roma" is signed "Hotel Hassler"). It does not override the match
    # check: the result is still scored, and a bad match still scores badly.
    name, city = cand.get('search_name') or cand['name'], cand.get('city', '')
    country = cand.get('country', '')
    out = {'slug': cand['slug'], 'name': name, 'city': city,
           'source': 'osm', 'attribution': ATTRIBUTION,
           'fields': {}, 'notes': []}

    try:
        results = query(name, city, country)
    except Exception as e:
        # A failed lookup is a finding and it gets a name. Anything that leaves a place unenriched
        # has to land in a queue with a reason on it, or the queue is just a list of things that
        # did not work.
        out['confidence'] = 0.0
        out['refusal'] = {'reason': 'lookup_failed', 'detail': str(e)[:200]}
        return out
    if not results:
        out['confidence'] = 0.0
        out['refusal'] = {'reason': 'no_external_match',
                          'detail': 'OpenStreetMap returned nothing for this name in this city'}
        return out

    # Score every candidate on name similarity, and prefer one whose city agrees with ours.
    # Our names often carry the city ("Hotel Sacher Vienna") because the slug does; OSM's do not.
    # Compare both ways and keep the better reading, or every such place scores as a near miss.
    ours = [name]
    if city:
        stripped = re.sub(r'\b' + re.escape(city) + r'\b', '', name, flags=re.I).strip(' ,-')
        if stripped and stripped != name:
            ours.append(stripped)

    best, best_score = None, 0.0
    for r in results:
        # Every name OSM records for the object, in any language, plus what the display name
        # leads with. A place is the same entity whether the map calls it Duomo di Milano or
        # Milan Cathedral, and asking in English means we are often handed the exonym.
        nd = r.get('namedetails') or {}
        names = [r.get('name') or '', (r.get('display_name') or '').split(',')[0]]
        names += [v for k, v in nd.items() if isinstance(v, str) and v
                  and not k.endswith(':prefix') and 'wikidata' not in k]
        score = max((similarity(o, n) for o in ours for n in names if n), default=0.0)
        addr = r.get('address') or {}
        got_city = addr.get('city') or addr.get('town') or addr.get('village') or addr.get('municipality') or ''
        if city and got_city and similarity(city, got_city) > 0.7:
            score += 0.15
        if score > best_score:
            best, best_score = r, score

    if best is None:
        out['confidence'] = 0.0
        out['refusal'] = {'reason': 'no_match', 'detail': 'nothing in the results could be scored'}
        return out

    out['confidence'] = round(min(best_score, 1.0), 3)
    out['osm_class'] = '%s/%s' % (best.get('category'), best.get('type'))

    # Classify a refusal rather than just failing it. "ambiguous name" and "the map says this is a
    # car park" need different fixes, and a queue of things labelled "failed" is not a queue.
    verdict, why = type_verdict(str(best.get('type') or ''), cand.get('type', ''))
    if verdict == 'unexpected':
        out['confidence'] = round(max(0.0, out['confidence'] - 0.10), 3)
        out['notes'].append(why)

    addr = best.get('address') or {}
    # A city is not only what Nominatim calls `city`. London's Ritz is in "City of Westminster",
    # Mexico City is "Ciudad de Mexico", Venice is "Venezia". Refusing those was the check being
    # wrong about administrative naming, not the data being wrong about geography -- so compare
    # against every level of the returned hierarchy, and against the full display name.
    levels = [addr.get(k) or '' for k in ('city', 'town', 'village', 'municipality', 'city_district',
                                          'county', 'state_district', 'state', 'region', 'suburb')]
    hay = fold(best.get('display_name') or '')
    needle = fold(city)
    geo_ok = (not city) or (needle and needle in hay) or any(
        lv and similarity(city, lv) > 0.6 for lv in levels)

    runners_up = sum(1 for r in results if r is not best
                     and max((similarity(o, r.get('name') or '') for o in ours), default=0) > 0.85)

    if verdict == 'never':
        out['refusal'] = {'reason': 'type_mismatch', 'detail': why}
    elif not geo_ok:
        out['refusal'] = {'reason': 'geographic_inconsistency',
                          'detail': 'we say %s, the match is in %s'
                                    % (city, (best.get('display_name') or '?')[:80])}
    elif out['confidence'] < 0.80 and runners_up:
        out['refusal'] = {'reason': 'ambiguous_name',
                          'detail': '%d other results score nearly as well' % runners_up}
    elif out['confidence'] < 0.80:
        out['refusal'] = {'reason': 'low_confidence',
                          'detail': 'best match "%s" scored %.2f' % (best.get('name') or '?', out['confidence'])}
    if out.get('refusal', {}).get('reason') == 'type_mismatch':
        out['confidence'] = 0.0          # the name is irrelevant once the kind is wrong

    out['osm'] = {'type': best.get('osm_type'), 'id': best.get('osm_id'),
                  'name': best.get('name'), 'display_name': best.get('display_name')}

    # Every other name the map records for this object, so search can find "Milan Cathedral" when
    # our page is called "Duomo di Milano" and vice versa. These are matching aids, never display
    # names: the page keeps the name we chose for it.
    nd = best.get('namedetails') or {}
    alias_pool = [best.get('name') or '']
    alias_pool += [v for k, v in nd.items()
                   if isinstance(v, str) and v and not k.endswith(':prefix') and 'wikidata' not in k]
    seen_alias = {fold(name)}
    aliases = []
    for a in alias_pool:
        f = fold(a)
        if not f or f in seen_alias:
            continue
        seen_alias.add(f)
        aliases.append(a)
    if aliases:
        out['aliases'] = aliases[:8]
    out['source_url'] = 'https://www.openstreetmap.org/%s/%s' % (best.get('osm_type'), best.get('osm_id'))

    addr = best.get('address') or {}
    tags = best.get('extratags') or {}

    house, road = addr.get('house_number'), addr.get('road')
    if road:
        out['fields']['street_address'] = street_line(road, house, addr.get('country_code'))
    if addr.get('postcode'):
        out['fields']['postal_code'] = addr['postcode']
    hood = addr.get('suburb') or addr.get('neighbourhood') or addr.get('quarter')
    if hood:
        out['fields']['neighborhood'] = hood
    if best.get('lat') and best.get('lon'):
        out['fields']['lat'] = best['lat']
        out['fields']['lng'] = best['lon']

    phone = tags.get('phone') or tags.get('contact:phone')
    if phone:
        out['fields']['phone'] = phone
    site = tags.get('website') or tags.get('contact:website') or tags.get('url')
    if site:
        out['fields']['website_url'] = site

    if cand.get('timezone'):
        out['fields']['timezone'] = cand['timezone']
        out['notes'].append('timezone supplied by hand; Nominatim does not return one')

    raw_hours = tags.get('opening_hours')
    if raw_hours:
        intervals, why = parse_opening_hours(raw_hours)
        if intervals:
            out['hours'] = intervals
            out['hours_raw'] = raw_hours
        else:
            out['notes'].append('opening_hours not stored (%s): %s' % (why, raw_hours))

    return out


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--candidates', required=True)
    ap.add_argument('--out', required=True)
    ap.add_argument('--sleep', type=float, default=1.1, help='seconds between Nominatim calls')
    # Batching is not a convenience. A hundred lookups applied in one go is a hundred chances to
    # write something wrong before anybody has looked at the first one.
    ap.add_argument('--offset', type=int, default=0)
    ap.add_argument('--limit', type=int, default=0, help='0 = all remaining')
    ap.add_argument('--only', default='',
                    help='comma-separated slugs, for re-running the ones a human just fixed')
    ap.add_argument('--append', action='store_true',
                    help='merge into an existing --out file by slug instead of replacing it')
    args = ap.parse_args()

    cands = json.load(open(args.candidates, encoding='utf-8'))
    if args.only:
        wanted = {x.strip() for x in args.only.split(',') if x.strip()}
        cands = [c for c in cands if c['slug'] in wanted]
    cands = cands[args.offset:]
    if args.limit:
        cands = cands[:args.limit]
    proposals = []
    for i, c in enumerate(cands):
        p = propose(c)
        fields = ', '.join(sorted(p['fields'])) or 'nothing'
        print('%-38s conf=%.2f  %s%s' % (c['slug'], p['confidence'], fields,
                                         '  +hours' if p.get('hours') else ''))
        if p.get('refusal'):
            print('    REFUSED [%s] %s' % (p['refusal']['reason'], p['refusal']['detail']))
        for n in p['notes']:
            print('    note: %s' % n)
        proposals.append(p)
        if i + 1 < len(cands):
            time.sleep(args.sleep)          # Nominatim usage policy: max 1 request/second

    existing = []
    if args.append and os.path.exists(args.out):
        try:
            existing = json.load(open(args.out, encoding='utf-8')).get('places', [])
        except Exception as e:
            print('could not read %s to append to (%s); writing fresh' % (args.out, e))
    # A later batch wins for a slug it re-proposed; everything else is carried through untouched.
    merged = {p['slug']: p for p in existing}
    for p in proposals:
        merged[p['slug']] = p

    doc = {'generated_at': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
           'source': 'openstreetmap/nominatim', 'attribution': ATTRIBUTION,
           'places': sorted(merged.values(), key=lambda p: p['slug'])}
    with open(args.out, 'w', encoding='utf-8') as f:
        json.dump(doc, f, indent=2, ensure_ascii=False)
        f.write('\n')
    reasons = {}
    for p in proposals:
        if p.get('refusal'):
            reasons[p['refusal']['reason']] = reasons.get(p['refusal']['reason'], 0) + 1
    ok = len(proposals) - sum(reasons.values())
    print('\n%d proposed, %d refused%s' % (ok, sum(reasons.values()),
          (': ' + ', '.join('%s=%d' % kv for kv in sorted(reasons.items()))) if reasons else ''))
    print('wrote %s (%d places in file, %d from this batch)'
          % (args.out, len(doc['places']), len(proposals)))
    return 0


if __name__ == '__main__':
    sys.exit(main())
