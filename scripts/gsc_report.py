"""What Google is already showing us for, so content work is aimed rather than sprayed.

V1_STATUS says to expand on evidence. This is the evidence: Search Console's own numbers, read
with the service account that owns the property, printed as three questions worth acting on.

  STRIKING DISTANCE  queries where a page already ranks 8-40. These are the ones a better page
                     can move onto the first screen. A query at position 75 is not a near miss,
                     it is a different page that does not exist yet.
  PAGES              which URLs earn impressions at all, so the internal linking can point at the
                     ones that work instead of at everything equally.
  NO PAGE FOR IT     queries we surface for on the homepage or a listing rather than on something
                     written to answer them. Each one is a page-shaped hole.

  python scripts/gsc_report.py [--days 28] [--json out.json]

Credentials: GOOGLE_APPLICATION_CREDENTIALS, or ~/google_credentials.json. The service account
must be a user on the property; ours is an owner.
"""
import argparse
import datetime
import json
import os
import sys
import urllib.parse
import urllib.request

SITE = 'https://ruinmytrip.com/'
API = 'https://searchconsole.googleapis.com/webmasters/v3'

# Above this, a query is not one better paragraph away from anything.
STRIKING_MAX = 40.0
STRIKING_MIN = 5.0

# Pages that answer a query by listing other pages. Ranking here means the answer page is missing.
LISTING_PATHS = ('/', '/explore', '/collections', '/reviews', '/guides', '/blog', '/talk',
                 '/communities', '/travelers', '/discover')


def token():
    try:
        from google.oauth2 import service_account
        import google.auth.transport.requests as tr
    except ImportError:
        sys.exit('pip install google-auth')
    path = os.environ.get('GOOGLE_APPLICATION_CREDENTIALS') or \
        os.path.join(os.path.expanduser('~'), 'google_credentials.json')
    if not os.path.exists(path):
        sys.exit(f'no credentials at {path}')
    creds = service_account.Credentials.from_service_account_file(
        path, scopes=['https://www.googleapis.com/auth/webmasters'])
    creds.refresh(tr.Request())
    return creds.token


def query(tok, start, end, dimensions, limit=200):
    body = json.dumps({'startDate': str(start), 'endDate': str(end),
                       'dimensions': dimensions, 'rowLimit': limit}).encode()
    site = urllib.parse.quote(SITE, safe='')
    req = urllib.request.Request(f'{API}/sites/{site}/searchAnalytics/query', data=body,
                                 headers={'Authorization': 'Bearer ' + tok,
                                          'Content-Type': 'application/json'})
    try:
        return json.load(urllib.request.urlopen(req)).get('rows', [])
    except urllib.error.HTTPError as e:
        sys.exit('search console: ' + e.read().decode()[:300])


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--days', type=int, default=28)
    ap.add_argument('--json')
    args = ap.parse_args()

    tok = token()
    end = datetime.date.today()
    start = end - datetime.timedelta(days=args.days)

    total = query(tok, start, end, [], 1)
    t = total[0] if total else {'clicks': 0, 'impressions': 0, 'position': 0}
    print(f"{args.days} days: {int(t['clicks'])} clicks, {int(t['impressions'])} impressions, "
          f"average position {t['position']:.0f}")

    pairs = query(tok, start, end, ['query', 'page'], 500)

    striking = [r for r in pairs if STRIKING_MIN <= r['position'] <= STRIKING_MAX]
    striking.sort(key=lambda r: (-r['impressions'], r['position']))
    print(f"\nSTRIKING DISTANCE ({len(striking)}): already ranking {int(STRIKING_MIN)}-{int(STRIKING_MAX)}")
    for r in striking[:25]:
        q, p = r['keys']
        print(f"  {r['position']:5.1f}  {int(r['impressions']):4d} imp  {q}\n         {p}")

    holes = [r for r in pairs
             if urllib.parse.urlparse(r['keys'][1]).path.rstrip('/') in
             [p.rstrip('/') for p in LISTING_PATHS]]
    holes.sort(key=lambda r: -r['impressions'])
    print(f"\nNO PAGE FOR IT ({len(holes)}): a listing is answering these")
    for r in holes[:20]:
        q, p = r['keys']
        print(f"  {r['position']:5.1f}  {int(r['impressions']):4d} imp  {q}  ->  {p}")

    pages = query(tok, start, end, ['page'], 100)
    pages.sort(key=lambda r: -r['impressions'])
    print(f"\nPAGES EARNING IMPRESSIONS ({len(pages)})")
    for r in pages[:20]:
        print(f"  {int(r['impressions']):4d} imp  {r['position']:5.1f}  {r['keys'][0]}")

    if args.json:
        with open(args.json, 'w', encoding='utf-8') as fh:
            json.dump({'total': t, 'striking': striking, 'holes': holes, 'pages': pages}, fh, indent=2)
        print(f"\nwrote {args.json}")


if __name__ == '__main__':
    main()
