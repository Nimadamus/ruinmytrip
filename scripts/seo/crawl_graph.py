"""Crawl ruinmytrip.com the way a search engine can: from the homepage, following only links
robots.txt allows, and record click depth, page type, robots meta, canonical and outlinks. Read only.

  python scripts/seo/crawl_graph.py --out DIR [--max 5000]

Writes graph.json (url -> depth, type, status, robots, canonical, outlinks) and summary.json:
click depth of every place page, how each place is first reached (city list, category, area,
nearby or similar on another place, guide, blog), and how many places a crawler cannot reach at all.
The agent says it is a bot, so the site's analytics never count it.
"""
import argparse, collections, html as html_lib, json, os, re, time, urllib.parse, urllib.request

SITE = 'https://ruinmytrip.com'
UA = 'Mozilla/5.0 (compatible; rmt-seo-audit-bot/1.0; +https://ruinmytrip.com/about)'


def get(url):
    for i in range(3):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': UA}), timeout=40) as r:
                ct = r.headers.get('Content-Type', '')
                return r.status, r.read().decode('utf-8', 'replace') if ('html' in ct or 'text/plain' in ct) else '', r.geturl()
        except urllib.error.HTTPError as e:
            return e.code, '', url
        except Exception:
            time.sleep(3 * (i + 1))
    return 0, '', url


def ptype(path):
    seg = path.strip('/').split('/')
    if path == '/':
        return 'home'
    if seg[0] == 'p':
        return 'place'
    if seg[0] == 'd':
        if len(seg) == 2:
            return 'city'
        if len(seg) >= 3 and seg[2] == 'places':
            return 'city_places'
        if len(seg) >= 3 and seg[2] == 'n':
            return 'area'
        if len(seg) >= 3 and seg[2] == 'travelers':
            return 'city_travelers'
        return 'city_category'
    return seg[0] or 'home'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', required=True)
    ap.add_argument('--max', type=int, default=5000)
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    # Google's matching, done by hand: the longest matching Allow or Disallow wins, '*' matches
    # anything, '$' anchors the end, and the query string is part of what is matched.
    _, robots, _ = get(SITE + '/robots.txt')
    rules, applies = [], False
    for line in robots.splitlines():
        line = line.split('#', 1)[0].strip()
        if not line or ':' not in line:
            continue
        k, v = [x.strip() for x in line.split(':', 1)]
        if k.lower() == 'user-agent':
            applies = v == '*' or 'googlebot' in v.lower()
        elif applies and k.lower() in ('allow', 'disallow') and v:
            rx = '^' + re.escape(v).replace(r'\*', '.*').replace(r'\$', '$')
            rules.append((len(v), k.lower() == 'allow', re.compile(rx)))

    def allowed(u):
        p = urllib.parse.urlsplit(u)
        path = (p.path or '/') + ('?' + p.query if p.query else '')
        best = None
        for ln, allow, rx in rules:
            if rx.match(path) and (best is None or ln > best[0] or (ln == best[0] and allow)):
                best = (ln, allow)
        return best is None or best[1]

    graph, depth, first_from = {}, {SITE + '/': 0}, {}
    q = collections.deque([SITE + '/'])
    while q and len(graph) < a.max:
        u = q.popleft()
        st, html, final = get(u)
        m = re.search(r'<meta name="robots" content="([^"]*)"', html)
        c = re.search(r'<link rel="canonical" href="([^"]*)"', html)
        outs = []
        for h in re.findall(r'href="([^"#]+)"', html):
            full = urllib.parse.urljoin(u, html_lib.unescape(h))
            if not full.startswith(SITE):
                continue
            path = urllib.parse.urlsplit(full).path
            if re.search(r'\.(css|js|png|jpe?g|webp|svg|ico|xml|txt|woff2?)$', path) or path.startswith(('/media/', '/assets/', '/card/')):
                continue
            if not allowed(full):
                continue
            full = full.split('#')[0]
            outs.append(full)
        outs = sorted(set(outs))
        path = urllib.parse.urlsplit(u).path
        graph[u] = {'depth': depth[u], 'type': ptype(path), 'status': st, 'robots': m.group(1) if m else '',
                    'canonical': c.group(1) if c else '', 'out': outs}
        for o in outs:
            if o not in depth:
                depth[o] = depth[u] + 1
                first_from[o] = u
                q.append(o)
        if len(graph) % 200 == 0:
            print('crawled', len(graph), 'queued', len(q), flush=True)
        time.sleep(0.1)

    with open(os.path.join(a.out, 'graph.json'), 'w') as f:
        json.dump({'pages': graph, 'first_from': first_from, 'depth': depth}, f)
    places = {u: v for u, v in graph.items() if v['type'] == 'place'}
    via = collections.Counter(ptype(urllib.parse.urlsplit(first_from.get(u, '')).path) if u in first_from else 'start'
                              for u in places)
    summary = {
        'pages_crawled': len(graph), 'queued_left': len(q),
        'types': collections.Counter(v['type'] for v in graph.values()),
        'place_depth': collections.Counter(v['depth'] for v in places.values()),
        'place_first_reached_from': via,
        'place_index_vs_noindex': collections.Counter(v['robots'][:7] for v in places.values()),
        'indexable_places_at_depth_4_plus': sum(1 for v in places.values() if v['robots'].startswith('index') and v['depth'] >= 4),
    }
    with open(os.path.join(a.out, 'summary.json'), 'w') as f:
        json.dump(summary, f, indent=1, default=dict)
    print(json.dumps(summary, indent=1, default=dict))


if __name__ == '__main__':
    main()
