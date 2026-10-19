"""Search snippet test: Search Console numbers for the test pages and a control group, one window.

  python scripts/gsc_snippet_test.py START END [--json out.json]     (dates YYYY-MM-DD, Pacific days)

Test pages come from database/meta_tests.json. Control pages are blog posts with impressions whose
description did not change, so a seasonal swing shows up in both groups. Read only.
"""
import argparse
import json
import os
import sys
import urllib.parse
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import gsc_report as g  # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CONTROL = ['/blog/san-francisco-hotel-tax-2026', '/blog/porto-tourist-tax-2026',
           '/blog/gold-museum-san-jose-tickets-2026', '/blog/tourist-taxes-2026']


def page_stats(tok, start, end, path):
    body = {'startDate': start, 'endDate': end, 'dimensions': [], 'rowLimit': 1,
            'dimensionFilterGroups': [{'filters': [{'dimension': 'page', 'operator': 'equals',
                                                    'expression': 'https://ruinmytrip.com' + path}]}]}
    req = urllib.request.Request(g.API + '/sites/' + urllib.parse.quote(g.SITE, safe='') + '/searchAnalytics/query',
                                 data=json.dumps(body).encode(),
                                 headers={'Authorization': 'Bearer ' + tok, 'Content-Type': 'application/json'})
    rows = json.load(urllib.request.urlopen(req)).get('rows', [])
    r = rows[0] if rows else {'clicks': 0, 'impressions': 0, 'position': None}
    return {'clicks': r['clicks'], 'impressions': r['impressions'],
            'ctr': round(r['clicks'] / r['impressions'], 4) if r['impressions'] else 0,
            'position': round(r['position'], 1) if r.get('position') else None}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('start')
    ap.add_argument('end')
    ap.add_argument('--json')
    a = ap.parse_args()
    tests = json.load(open(os.path.join(ROOT, 'database', 'meta_tests.json'), encoding='utf-8'))['tests']
    tok = g.token()
    out = {'window': [a.start, a.end], 'test': {}, 'control': {}}
    for group, paths in (('test', list(tests)), ('control', CONTROL)):
        for p in paths:
            out[group][p] = s = page_stats(tok, a.start, a.end, p)
            print(f"{group:8} {s['impressions']:5} imp {s['clicks']:3} clicks  ctr {s['ctr']:.3f}  pos {s['position']}  {p}")
    if a.json:
        json.dump(out, open(a.json, 'w'), indent=1)


if __name__ == '__main__':
    main()
