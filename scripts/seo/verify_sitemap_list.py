"""Check the curated places sitemap list against the live site before it is ever switched on. Read only.

  python scripts/seo/verify_sitemap_list.py --out DIR [--gsc pages90.json]

For every slug in database/sitemap_places.txt, fetched live from https://ruinmytrip.com:
  status (and no redirect), robots meta, canonical equal to the URL, title, words of page text, words
  that are this page's own (not template text shared by most of the list), and the most similar
  other page on the list (5 word shingles), so thin or near duplicate pages show up.
Then the hubs a crawler uses (home, every city page, its place list and its category pages):
  how many hub links reach each listed place, whether any hub links a variant of the URL (query,
  trailing slash, other case), and what the homepage spends its links on (indexable or not).
With --gsc (a Search Console page export, 90 days): impressions for listed pages against indexable
  places left off the list, so the priorities can be checked against search data.
The agent says it is a bot, so the site's analytics never count it.
"""
import argparse, collections, html as H, json, os, re, time, urllib.parse, urllib.request

SITE = 'https://ruinmytrip.com'
UA = 'Mozilla/5.0 (compatible; rmt-seo-audit-bot/1.0; +https://ruinmytrip.com/about)'


def get(url):
    for i in range(3):
        try:
            req = urllib.request.Request(url, headers={'User-Agent': UA})
            with urllib.request.urlopen(req, timeout=40) as r:
                return r.status, r.read().decode('utf-8', 'replace'), r.geturl()
        except urllib.error.HTTPError as e:
            return e.code, '', url
        except Exception:
            time.sleep(3 * (i + 1))
    return 0, '', url


def meta(html):
    one = lambda rx: (re.search(rx, html, re.S) or [None, None])[1]
    return {'robots': one(r'<meta name="robots" content="([^"]*)"'),
            'canonical': one(r'<link rel="canonical" href="([^"]*)"'),
            'title': H.unescape(one(r'<title>(.*?)</title>') or '')}


def main_text(html):
    m = re.search(r'<main[^>]*>(.*)</main>', html, re.S)
    body = m.group(1) if m else html
    body = re.sub(r'<(script|style|nav|footer|form)\b.*?</\1>', ' ', body, flags=re.S)
    return re.sub(r'\s+', ' ', H.unescape(re.sub(r'<[^>]+>', ' ', body))).strip()


def place_links(html):
    out = []
    for h in re.findall(r'href="([^"#]*)"', html):
        h = H.unescape(h)
        u = urllib.parse.urljoin(SITE + '/', h)
        if urllib.parse.urlsplit(u).path.startswith('/p/') and u.startswith(SITE):
            out.append(u)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', required=True)
    ap.add_argument('--gsc')
    ap.add_argument('--list', default='database/sitemap_places.txt')
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    slugs = [l.strip() for l in open(a.list, encoding='utf-8') if l.strip() and not l.startswith('#')]

    pages = {}
    for s in slugs:
        url = f'{SITE}/p/{s}'
        st, html, final = get(url)
        m = meta(html)
        txt = main_text(html)
        pages[s] = {'status': st, 'redirected': final.rstrip('/') != url, 'robots': m['robots'],
                    'canonical_ok': m['canonical'] == url, 'canonical': m['canonical'], 'title': m['title'],
                    'words': len(txt.split()), '_txt': txt}
        time.sleep(0.15)

    # Template text: words that appear on most listed pages are the page frame, not content.
    df = collections.Counter()
    for p in pages.values():
        df.update(set(w.lower() for w in re.findall(r"[A-Za-z][A-Za-z']+", p['_txt'])))
    common = {w for w, n in df.items() if n >= 0.5 * len(pages)}
    shingles = {}
    for s, p in pages.items():
        ws = [w.lower() for w in re.findall(r"[A-Za-z][A-Za-z']+", p['_txt'])]
        p['own_words'] = sum(1 for w in ws if w not in common)
        own = [w for w in ws if w not in common]
        shingles[s] = {' '.join(own[i:i + 5]) for i in range(max(0, len(own) - 4))}
    for s in pages:
        best, who = 0.0, ''
        for t in pages:
            if t == s or not shingles[s] or not shingles[t]:
                continue
            j = len(shingles[s] & shingles[t]) / len(shingles[s] | shingles[t])
            if j > best:
                best, who = j, t
        pages[s]['max_similarity'] = round(best, 3)
        pages[s]['most_similar'] = who

    # Hubs: home, cities, their place lists and category pages.
    st, home, _ = get(SITE + '/')
    cities = sorted(set(re.findall(r'href="(?:https://ruinmytrip\.com)?/d/([a-z0-9-]+)"', get(SITE + '/explore')[1])))
    hubs = [SITE + '/']
    for c in cities:
        hubs += [f'{SITE}/d/{c}', f'{SITE}/d/{c}/places']
        for cat in ('restaurants', 'things-to-do', 'hotels', 'experiences'):
            hubs.append(f'{SITE}/d/{c}/{cat}')
    inlinks = collections.Counter()
    variants = collections.Counter()
    hub_ok = 0
    for h in hubs:
        st, html, final = get(h)
        if st != 200:
            continue
        hub_ok += 1
        for u in set(place_links(html)):
            path = urllib.parse.urlsplit(u)
            slug = path.path[3:]
            if slug.rstrip('/') in pages:
                if path.query or slug.endswith('/') or slug != slug.lower():
                    variants[slug.rstrip('/')] += 1
                else:
                    inlinks[slug] += 1
        time.sleep(0.15)
    for s in pages:
        pages[s]['hub_inlinks'] = inlinks[s]
        pages[s]['variant_links'] = variants[s]

    # What the homepage links to, and whether those pages can be indexed.
    home_links = sorted(set(urllib.parse.urljoin(SITE + '/', H.unescape(h)).split('#')[0]
                            for h in re.findall(r'href="([^"]+)"', home)
                            if urllib.parse.urljoin(SITE + '/', H.unescape(h)).startswith(SITE)
                            and not re.search(r'\.(css|js|png|svg|xml|ico|webmanifest)(\?|$)', h)))
    home_report = []
    for u in home_links:
        st, html, final = get(u)
        home_report.append({'url': u, 'status': st, 'robots': meta(html)['robots'] if html else None})
        time.sleep(0.1)

    gsc = {}
    if a.gsc:
        for r in json.load(open(a.gsc, encoding='utf-8')):
            u = r['keys'][0]
            if '/p/' in u:
                gsc[u.split('/p/')[1].rstrip('/')] = (int(r['impressions']), int(r['clicks']))
    for s in pages:
        pages[s]['impressions_90d'], pages[s]['clicks_90d'] = gsc.get(s, (0, 0))
        del pages[s]['_txt']

    summary = {
        'listed': len(pages),
        'status_200_no_redirect': sum(1 for p in pages.values() if p['status'] == 200 and not p['redirected']),
        'robots_index': sum(1 for p in pages.values() if (p['robots'] or '').startswith('index')),
        'self_canonical': sum(1 for p in pages.values() if p['canonical_ok']),
        'words_median': sorted(p['words'] for p in pages.values())[len(pages) // 2],
        'own_words_min': min(p['own_words'] for p in pages.values()),
        'own_words_median': sorted(p['own_words'] for p in pages.values())[len(pages) // 2],
        'own_words_under_80': sorted(s for s, p in pages.items() if p['own_words'] < 80),
        'near_duplicates_over_0.5': sorted((s, p['most_similar'], p['max_similarity']) for s, p in pages.items() if p['max_similarity'] > 0.5),
        'hubs_fetched': hub_ok,
        'listed_with_0_hub_links': sorted(s for s, p in pages.items() if p['hub_inlinks'] == 0),
        'listed_hub_links_median': sorted(p['hub_inlinks'] for p in pages.values())[len(pages) // 2],
        'variant_links_total': sum(variants.values()),
        'home_links': len(home_report),
        'home_links_noindex': [h['url'] for h in home_report if h['robots'] and not h['robots'].startswith('index')],
        'home_links_not_200': [h['url'] for h in home_report if h['status'] != 200],
        'home_place_links': sum(1 for h in home_report if '/p/' in h['url']),
        'listed_with_impressions': sum(1 for p in pages.values() if p['impressions_90d'] > 0),
        'listed_impressions_90d': sum(p['impressions_90d'] for p in pages.values()),
        'unlisted_place_pages_with_impressions': sorted(((s, i) for s, (i, c) in gsc.items() if s not in pages and i >= 5),
                                                        key=lambda x: -x[1]),
    }
    json.dump({'summary': summary, 'pages': pages, 'home': home_report}, open(os.path.join(a.out, 'verify.json'), 'w'), indent=1)
    print(json.dumps(summary, indent=1))


if __name__ == '__main__':
    main()
