"""Rank every live place page for a controlled sitemap restoration. Read only.

  python scripts/seo/place_candidates.py --out DIR

1. Lists every place from each city's full place list (/d/{slug}/places?all=1).
2. Fetches every place page once, politely (one at a time), as an agent that says it is a bot so the
   site's own analytics never count it.
3. Reads from each page: robots meta, canonical, whether it is the page's own URL, and the real
   signals the page carries (address, phone, website, opening hours, RuinMyTrip guide, traveler
   reviews, editorial review, photos), plus every /p/ link on it (the internal link graph).
4. Joins Search Console impressions, clicks and position since 2026-06-01 per URL.
5. Scores and writes candidates.csv (all places) and shortlist.txt (the slugs that pass).

Shortlist rule (docs/SEO_RECOVERY_20261011.md): indexable and self canonical, AND three or more
signals, AND (earned impressions since June OR has a guide or a review), ranked by impressions,
then signals, then inbound links. Capped at 250.
"""
import argparse, csv, datetime as D, json, os, re, sys, time, urllib.request, urllib.parse
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))
import gsc_report  # noqa: E402

SITE = 'https://ruinmytrip.com'
UA = {'User-Agent': 'Mozilla/5.0 (compatible; rmt-seo-audit-bot/1.0; +https://ruinmytrip.com/about)'}


def get(url, tries=3):
    for i in range(tries):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=40) as r:
                return r.status, r.read().decode('utf-8', 'replace')
        except urllib.error.HTTPError as e:
            return e.code, ''
        except Exception:
            time.sleep(3 * (i + 1))
    return 0, ''


def gsc_pages():
    t = gsc_report.token()
    body = {'startDate': '2026-06-01', 'endDate': str(D.date.today()), 'dimensions': ['page'], 'rowLimit': 25000}
    site = urllib.parse.quote(gsc_report.SITE, safe='')
    r = urllib.request.Request(gsc_report.API + '/sites/%s/searchAnalytics/query' % site, data=json.dumps(body).encode(),
                               headers={'Authorization': 'Bearer ' + t, 'Content-Type': 'application/json'})
    rows = json.loads(urllib.request.urlopen(r).read()).get('rows', [])
    return {x['keys'][0].replace(SITE, ''): x for x in rows}


def signals(html):
    return {
        'address': '>Address</dt>' in html,
        'phone': '>Phone</dt>' in html,
        'website': '>Website</dt>' in html,
        'hours': ('Opening hours</h3>' in html) or ('Hours it kept</h3>' in html),
        'guide': 'RuinMyTrip guide to' in html,
        'editorial_review': 'From the RuinMyTrip team' in html,
        'traveler_reviews': 'What travelers said' in html,
        'photos': bool(re.search(r'<h2[^>]*>Photos?</h2>', html)),
    }


def links(html):
    return set(re.findall(r'href="https://ruinmytrip.com/p/([a-z0-9-]+)"', html))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', required=True)
    ap.add_argument('--limit', type=int, default=0)
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    _, sm = get(SITE + '/sitemap-destinations.xml')
    cities = sorted(set(re.findall(r'<loc>https://ruinmytrip.com/d/([a-z0-9-]+)</loc>', sm)))
    places, inbound = {}, {}
    for c in cities:
        _, html = get(SITE + '/d/' + c + '/places?all=1')
        for s in links(html):
            places.setdefault(s, c)
        # What a crawler can actually reach: the list without the query (robots.txt blocks /d/*?).
        _, html = get(SITE + '/d/' + c + '/places')
        for s in links(html):
            inbound.setdefault(s, set()).add('/d/' + c + '/places')
        _, html = get(SITE + '/d/' + c)
        for s in links(html):
            inbound.setdefault(s, set()).add('/d/' + c)
        time.sleep(0.3)
    print('cities', len(cities), 'places', len(places), flush=True)
    g = gsc_pages()
    rows = []
    for i, (slug, city) in enumerate(sorted(places.items())):
        if a.limit and i >= a.limit:
            break
        st, html = get(SITE + '/p/' + slug)
        m = re.search(r'<meta name="robots" content="([^"]*)"', html)
        robots = m.group(1) if m else ''
        m = re.search(r'<link rel="canonical" href="([^"]*)"', html)
        canon = m.group(1) if m else ''
        sig = signals(html)
        for s in links(html):
            if s != slug:
                inbound.setdefault(s, set()).add('/p/' + slug)
        gs = g.get('/p/' + slug, {})
        row = {'slug': slug, 'city': city, 'status': st, 'robots': robots,
               'self_canonical': int(canon == SITE + '/p/' + slug)}
        row.update({k: int(v) for k, v in sig.items()})
        row.update({'signals': sum(sig.values()), 'impressions': int(gs.get('impressions', 0)),
                    'clicks': int(gs.get('clicks', 0)), 'position': round(gs.get('position', 0), 1) if gs else ''})
        rows.append(row)
        if i % 100 == 0:
            print('fetched', i, flush=True)
        time.sleep(0.25)
    for r in rows:
        src = inbound.get(r['slug'], set())
        r['inbound_links'] = len(src)
        r['in_city_list'] = int(any(x.endswith('/places') for x in src))
        r['eligible'] = int(r['status'] == 200 and r['robots'].startswith('index') and r['self_canonical'] == 1
                            and r['signals'] >= 3
                            and (r['impressions'] > 0 or r['guide'] or r['editorial_review'] or r['traveler_reviews']))
    rows.sort(key=lambda r: (-r['eligible'], -r['impressions'], -r['signals'], -r['inbound_links'], r['slug']))
    with open(os.path.join(a.out, 'candidates.csv'), 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows(rows)
    short = [r['slug'] for r in rows if r['eligible']][:250]
    with open(os.path.join(a.out, 'shortlist.txt'), 'w') as f:
        f.write('\n'.join(short) + '\n')
    summary = {
        'places': len(rows), 'status_200': sum(r['status'] == 200 for r in rows),
        'index': sum(r['robots'].startswith('index') for r in rows),
        'noindex': sum(r['robots'].startswith('noindex') for r in rows),
        'self_canonical': sum(r['self_canonical'] for r in rows),
        'signals_dist': {n: sum(r['signals'] == n for r in rows) for n in range(0, 9)},
        'with_impressions': sum(r['impressions'] > 0 for r in rows),
        'in_crawlable_city_list': sum(r['in_city_list'] for r in rows),
        'zero_inbound_links': sum(r['inbound_links'] == 0 for r in rows),
        'eligible': sum(r['eligible'] for r in rows), 'shortlist': len(short),
    }
    with open(os.path.join(a.out, 'summary.json'), 'w') as f:
        json.dump(summary, f, indent=1)
    print(json.dumps(summary, indent=1))


if __name__ == '__main__':
    main()
