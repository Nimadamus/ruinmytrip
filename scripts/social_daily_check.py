"""
The daily check that something obvious is not broken, once posting has started.

Checks, and never changes anything:
  * every tracked link scheduled for today and the next two days answers 200 and shows its landing banner;
  * each of those pages' share preview image loads (what Facebook and WhatsApp will show);
  * the site is up and on the expected migration; the trip form renders;
  * tracking is firing: arrivals and engaged visitors were recorded in the last 24 hours;
  * a platform that was scheduled to post yesterday produced at least one landing (a hint that a post
    did not publish or its link is wrong; zero on day one is normal for a new account, so it warns).

Writes ~/rmt_social/checks/<date>.json and prints one line per problem. Exit 1 on a failure.
Runs with a bot user agent, so none of it is counted as a visit.

  python scripts/social_daily_check.py [--start 2026-09-28]
"""
import argparse
import datetime as dt
import json
import os
import re
import sys
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from social_kit import SITE, link  # noqa: E402
from social_from_site import cron_key  # noqa: E402

UA = 'RMT-daily-check-bot/1.0'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def get(url, ua=UA):
    req = urllib.request.Request(url, headers={'User-Agent': ua})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, r.read().decode('utf-8', 'replace'), r.headers.get('Content-Type', '')
    except urllib.error.HTTPError as e:
        return e.code, '', ''
    except Exception as e:  # network down is itself the finding
        return 0, str(e), ''


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--today', default=dt.date.today().isoformat())
    a = ap.parse_args()
    today = dt.date.fromisoformat(a.today)
    bank = json.load(open(os.path.join(ROOT, 'docs', 'social', 'content_bank.json'), encoding='utf-8'))
    byid = {p['id']: p for p in bank['posts']}
    cal = bank['calendar']
    start = dt.date.fromisoformat(cal['start'])
    fails, warns, oks = [], [], []

    # 1. The next three days of links, and their share previews.
    for k in range(3):
        d = today + dt.timedelta(days=k)
        i = (d - start).days
        if i < 0 or i >= len(cal['days']):
            continue
        p = byid[cal['days'][i]]
        u = link(p['link_path'], 'facebook', 'post', p['id'], bank['campaign'])
        code, html, _ = get(u)
        if code != 200:
            fails.append('%s %s link answered %s' % (d, p['id'], code))
            continue
        if 'From our Facebook post' not in html:
            fails.append('%s %s landing banner missing' % (d, p['id']))
        code, og, _ = get(SITE + p['link_path'], 'facebookexternalhit/1.1')
        m = re.search(r'og:image" content="([^"]+)"', og)
        if not m:
            fails.append('%s %s no og:image' % (d, p['id']))
        else:
            c2, _, ct = get(m.group(1))
            if c2 != 200 or not ct.startswith('image/'):
                fails.append('%s %s share image %s %s' % (d, p['id'], c2, ct))
        oks.append('%s %s' % (d, p['id']))

    # 2. The site and the trip form.
    code, body, _ = get(SITE + '/readyz')
    if code != 200 or 'db=ok' not in body:
        fails.append('readyz %s %s' % (code, body[:80]))
    code, body, _ = get(SITE + '/plan')
    if code != 200 or 'id="plan-form"' not in body:
        fails.append('trip form did not render (%s)' % code)

    # 3. Tracking, from the funnel.
    code, body, _ = get(SITE + '/cron/funnel?days=1&key=' + cron_key())
    if code != 200:
        fails.append('funnel endpoint %s' % code)
    else:
        sc = json.loads(body)['scorecard']
        if sc['visitors']['likely_human'] == 0:
            fails.append('no arrivals recorded in 24 hours: tracking may be broken')
        if sc['visitors']['engaged'] == 0:
            warns.append('no engaged visitor in 24 hours (tracking, or simply no people yet)')
        y = today - dt.timedelta(days=1)
        if 0 <= (y - start).days < len(cal['days']):
            for row in sc.get('by_source', []):
                if row['source'] in ('facebook', 'instagram', 'tiktok') and row['landed'] == 0:
                    warns.append('%s was scheduled yesterday and brought 0 landings: check it published' % row['source'])
        oks.append('funnel read: %s engaged, %s likely human' % (sc['visitors']['engaged'], sc['visitors']['likely_human']))

    out = os.path.expanduser('~/rmt_social/checks')
    os.makedirs(out, exist_ok=True)
    json.dump({'date': today.isoformat(), 'fails': fails, 'warns': warns, 'ok': oks},
              open(os.path.join(out, today.isoformat() + '.json'), 'w', encoding='utf-8'), indent=1)
    for f in fails:
        print('FAIL', f)
    for w in warns:
        print('WARN', w)
    print('%d fail, %d warn, %d ok' % (len(fails), len(warns), len(oks)))
    sys.exit(1 if fails else 0)


if __name__ == '__main__':
    main()
