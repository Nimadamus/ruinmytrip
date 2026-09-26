"""
Turn what is happening on RuinMyTrip into social post candidates.

Reads /cron/social (aggregates of member activity, recent questions, warnings) and writes, for the
day it runs:
  ~/rmt_social/site/<date>/<id>/slide*.png and copy.txt
  ~/rmt_social/site/<date>/candidates.json   every candidate, with review true or false

Rules it keeps:
  * members are only ever counted, never named, never one person's dates;
  * a member's question, and any research warning (it makes a claim about a place), is review: true,
    so a person approves it before it goes out; the team's own questions and the counts are not;
  * a candidate already produced on an earlier day is not produced again (ledger.json).

  python scripts/social_from_site.py            (reads CRON_KEY from the env, else CREDENTIALS.md)
"""
import datetime as dt
import json
import os
import re
import sys
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from social_kit import SITE, link, slide, tags  # noqa: E402

CAMPAIGN = 'site_v1'
OUT = os.path.expanduser('~/rmt_social/site')


def cron_key():
    k = os.environ.get('CRON_KEY', '')
    if k:
        return k
    txt = open(os.path.expanduser('~/CREDENTIALS.md'), encoding='utf-8', errors='ignore').read()
    for block in re.split(r'\n\s*\n', txt):
        if re.search(r'ruinmytrip|rmt', block, re.I):
            m = re.search(r'CRON_KEY[^A-Za-z0-9]*([A-Za-z0-9_-]{16,})', block)
            if m:
                return m.group(1)
    sys.exit('no CRON_KEY')


def candidates(feed):
    out = []
    for c in feed.get('cities_going', []):
        out.append({'id': 'going_' + c['slug'], 'pillar': 'travel buddy discussions', 'review': False,
                    'path': '/d/%s/travelers' % c['slug'], 'place': c['name'],
                    'slides': ['Going to %s soon?' % c['name'], '%d travelers already' % int(c['n']), 'have dates there.', 'Are yours the same week?'],
                    'text': '%d travelers have upcoming dates in %s. Going too? Add yours and see who overlaps.' % (int(c['n']), c['name'])})
    for c in feed.get('buddy_countries', []):
        out.append({'id': 'buddies_' + c['slug'], 'pillar': 'travel buddy discussions', 'review': False,
                    'path': '/buddies?dest=%s' % c['slug'], 'place': c['name'],
                    'slides': ['Travel buddies wanted', '%d open requests' % int(c['n']), 'for %s.' % c['name'], 'Going around then?'],
                    'text': 'There are %d open travel buddy requests for %s right now. Going around then? Have a look.' % (int(c['n']), c['name'])})
    for q in feed.get('questions', []):
        place = q.get('dest_name') or ''
        out.append({'id': 'q_%d' % q['id'], 'pillar': 'destination debates', 'review': bool(q['review']),
                    'path': '/post/%d' % q['id'], 'place': place,
                    'slides': ['A traveler asked', q['body'][:120], 'Can you help?'],
                    'text': 'A traveler asked%s: "%s" Can you help? Answer here.' % ((' about ' + place) if place else '', q['body'])})
    for w in feed.get('warnings', []):
        place = w.get('dest_name') or ''
        out.append({'id': 'w_%d' % w['id'], 'pillar': 'tourist traps', 'review': True,
                    'path': '/review/%d' % w['id'], 'place': place,
                    'slides': ['What went wrong%s' % ((' in ' + place) if place else ''), w['text'][:120], 'Would you have seen it coming?'],
                    'text': 'What went wrong%s: "%s" Would you have seen it coming? More warnings like this on RuinMyTrip.' % ((' in ' + place) if place else '', w['text'])})
    return out


def main():
    req = urllib.request.Request(SITE + '/cron/social?key=' + cron_key(), headers={'User-Agent': 'RMT-social-feed-bot/1.0'})
    feed = json.load(urllib.request.urlopen(req, timeout=60))
    today = dt.date.today().isoformat()
    os.makedirs(OUT, exist_ok=True)
    ledger_f = os.path.join(OUT, 'ledger.json')
    ledger = json.load(open(ledger_f, encoding='utf-8')) if os.path.exists(ledger_f) else {}
    day = os.path.join(OUT, today)
    made = []
    for c in candidates(feed):
        if c['id'] in ledger:
            continue
        cdir = os.path.join(day, c['id'])
        os.makedirs(cdir, exist_ok=True)
        for j, s in enumerate(c['slides']):
            slide(s, j, len(c['slides']), c['pillar'], os.path.join(cdir, 'slide%d.png' % (j + 1)))
        fb = c['text'] + '\n\n' + link(c['path'], 'facebook', 'post', c['id'], CAMPAIGN)
        cap = c['text'] + ' Link in bio. ' + tags(c['pillar'], c['place'])
        open(os.path.join(cdir, 'copy.txt'), 'w', encoding='utf-8').write(
            ('NEEDS REVIEW before posting\n\n' if c['review'] else '') + 'FACEBOOK\n' + fb + '\n\nINSTAGRAM AND TIKTOK\n' + cap + '\n')
        c.update({'folder': cdir, 'facebook': fb, 'caption': cap})
        made.append(c)
        ledger[c['id']] = today
    if made:
        json.dump(made, open(os.path.join(day, 'candidates.json'), 'w', encoding='utf-8'), indent=1, ensure_ascii=False)
    json.dump(ledger, open(ledger_f, 'w', encoding='utf-8'), indent=1)
    print('%d new candidates (%d need review) -> %s' % (len(made), sum(1 for c in made if c['review']), day))


if __name__ == '__main__':
    main()
