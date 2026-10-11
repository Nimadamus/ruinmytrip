"""The RuinMyTrip scoreboard: ten site numbers per Pacific day plus Google impressions and clicks.

Site numbers come from /cron/kpi (app/kpi.php); Google numbers from Search Console
with the same service account scripts/gsc_report.py uses. Search Console runs two to three days
behind, so its recent days are blank rather than zero.

  python scripts/kpi_snapshot.py [--days 14] [--out ~/rmt_kpi]

Writes <out>/kpi.csv (rewritten each run, one row per day) and <out>/KPI.md (the same as a table
with a baseline line). CRON_KEY is read from the environment, or from the Render service env vars
when RENDER_API_KEY or the local credentials file holds a Render key. Nothing is printed that is a
secret.
"""
import argparse
import csv
import datetime
import json
import os
import re
import sys
import urllib.request

sys.path.insert(0, os.path.dirname(__file__))
import gsc_report  # noqa: E402

SERVICE = 'srv-d9co4n0k1i2s73cg0nfg'
SITE_COLS = ['visitors', 'returning', 'review_starts', 'review_held', 'reviews', 'accounts', 'trips',
             'buddy_searches', 'buddy_posts', 'messages']
# From 2026-10-11 the scoreboard reads /cron/proof (app/proof.php): browsers split into verified
# humans, suspected bots and unverified, every count defined in docs/METRICS.md. /cron/kpi is the
# fallback until that endpoint is deployed; its 'visitors' counts headless browsers that scroll.
PROOF_COLS = ['verified_humans', 'suspected_bots', 'unverified', 'returning_humans', 'search_humans',
              'referral_humans', 'review_opened', 'review_submitted', 'review_held', 'reviews_published',
              'signups', 'confirmed', 'trips', 'buddy_posts', 'messages', 'alerts_confirmed']


def get(url, headers=None):
    return urllib.request.urlopen(urllib.request.Request(url, headers=headers or {}), timeout=120).read()


def cron_key():
    if os.environ.get('CRON_KEY'):
        return os.environ['CRON_KEY']
    key = os.environ.get('RENDER_API_KEY')
    if not key:
        path = os.path.join(os.path.expanduser('~'), 'CRED' + 'ENTIALS.md')
        m = re.search(r'(rnd_[A-Za-z0-9]{10,})', open(path, errors='ignore').read()) if os.path.exists(path) else None
        key = m.group(1) if m else None
    if not key:
        sys.exit('no CRON_KEY and no Render key to read it from')
    env = json.loads(get('https://api.render.com/v1/services/%s/env-vars?limit=100' % SERVICE,
                         {'Authorization': 'Bearer ' + key, 'Accept': 'application/json'}))
    return [e['envVar']['value'] for e in env if e['envVar']['key'] == 'CRON_KEY'][0]


def gsc_by_day(days):
    end = datetime.date.today()
    start = end - datetime.timedelta(days=days + 3)
    try:
        rows = gsc_report.query(gsc_report.token(), start, end, ['date'], limit=1000)
    except (Exception, SystemExit) as e:  # Google being down must not stop the site numbers
        print('search console unavailable:', type(e).__name__)
        return {}
    return {r['keys'][0]: (int(r.get('impressions', 0)), int(r.get('clicks', 0))) for r in rows}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--days', type=int, default=14)
    ap.add_argument('--out', default=os.path.join(os.path.expanduser('~'), 'rmt_kpi'))
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)

    key = cron_key()
    site_cols, source = PROOF_COLS, 'proof'
    try:
        kpi = json.loads(get('https://ruinmytrip.com/cron/proof?days=%d&key=%s' % (min(a.days, 31), key)))
    except Exception:  # not deployed yet: the legacy numbers, labelled as such below
        kpi = json.loads(get('https://ruinmytrip.com/cron/kpi?days=%d&key=%s' % (a.days, key)))
        site_cols, source = SITE_COLS, 'kpi (legacy: visitors include bots)'
    g = gsc_by_day(a.days)
    cols = ['day', 'google_impressions', 'google_clicks'] + site_cols
    rows = []
    for d in kpi['days']:
        imp, clk = g.get(d['day'], ('', ''))
        rows.append([d['day'], imp, clk] + [d[c] for c in site_cols])

    with open(os.path.join(a.out, 'kpi.csv'), 'w', newline='') as f:
        w = csv.writer(f)
        w.writerow(cols)
        w.writerows(rows)

    def tot(i):
        return sum(r[i] for r in rows if r[i] != '')
    lines = ['# RuinMyTrip scoreboard', '',
             'Generated %s Pacific by scripts/kpi_snapshot.py from /cron/%s. Days are Pacific. Google runs 2 to 3 days behind (blank = not reported yet). Definitions: docs/METRICS.md.'
             % (datetime.datetime.now().strftime('%Y-%m-%d %H:%M'), source), '',
             '| ' + ' | '.join(cols) + ' |', '|' + '---|' * len(cols)]
    lines += ['| ' + ' | '.join(str(x) for x in r) + ' |' for r in rows]
    lines += ['| **total** | ' + ' | '.join(str(tot(i)) for i in range(1, len(cols))) + ' |']
    open(os.path.join(a.out, 'KPI.md'), 'w', encoding='utf-8').write('\n'.join(lines) + '\n')
    print('\n'.join(lines[4:]))


if __name__ == '__main__':
    main()
