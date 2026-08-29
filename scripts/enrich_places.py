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
import json
import re
import sys
import time
import unicodedata
import urllib.parse
import urllib.request
from difflib import SequenceMatcher

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
    return SequenceMatcher(None, fold(a), fold(b)).ratio()


def query(name: str, city: str, country: str) -> list[dict]:
    params = urllib.parse.urlencode({
        'q': '%s, %s, %s' % (name, city, country),
        'format': 'jsonv2', 'limit': '5',
        'addressdetails': '1', 'extratags': '1', 'namedetails': '1',
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
                c = '00:00'
            for d in days:
                intervals.append({'day_of_week': d, 'opens': o, 'closes': c})
    return intervals, None


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
    except Exception as e:                                   # a failed lookup is a finding
        out['notes'].append('lookup failed: %s' % e)
        out['confidence'] = 0.0
        return out
    if not results:
        out['notes'].append('no OSM match')
        out['confidence'] = 0.0
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
        names = [r.get('name') or '', (r.get('namedetails') or {}).get('name:en') or '',
                 (r.get('display_name') or '').split(',')[0]]
        score = max((similarity(o, n) for o in ours for n in names if n), default=0.0)
        addr = r.get('address') or {}
        got_city = addr.get('city') or addr.get('town') or addr.get('village') or addr.get('municipality') or ''
        if city and got_city and similarity(city, got_city) > 0.7:
            score += 0.15
        if score > best_score:
            best, best_score = r, score

    if best is None:
        out['notes'].append('no scoreable match')
        out['confidence'] = 0.0
        return out

    out['confidence'] = round(min(best_score, 1.0), 3)
    out['osm'] = {'type': best.get('osm_type'), 'id': best.get('osm_id'),
                  'name': best.get('name'), 'display_name': best.get('display_name')}
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
    args = ap.parse_args()

    cands = json.load(open(args.candidates, encoding='utf-8'))
    proposals = []
    for i, c in enumerate(cands):
        p = propose(c)
        fields = ', '.join(sorted(p['fields'])) or 'nothing'
        print('%-38s conf=%.2f  %s%s' % (c['slug'], p['confidence'], fields,
                                         '  +hours' if p.get('hours') else ''))
        for n in p['notes']:
            print('    note: %s' % n)
        proposals.append(p)
        if i + 1 < len(cands):
            time.sleep(args.sleep)          # Nominatim usage policy: max 1 request/second

    doc = {'generated_at': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
           'source': 'openstreetmap/nominatim', 'attribution': ATTRIBUTION,
           'places': proposals}
    with open(args.out, 'w', encoding='utf-8') as f:
        json.dump(doc, f, indent=2, ensure_ascii=False)
        f.write('\n')
    print('\nwrote %s (%d places)' % (args.out, len(proposals)))
    return 0


if __name__ == '__main__':
    sys.exit(main())
