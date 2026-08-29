"""Crawl the live site and answer three questions about its link graph.

  1. ORPHANS      -- is anything we tell crawlers to index unreachable by following links?
  2. CONTRADICTIONS -- does anything say noindex while sitting in the sitemap, or carry a canonical
                     pointing somewhere other than itself?
  3. DEAD ENDS    -- does any link on the site lead to a 404 or a redirect?

A sitemap is a hint. Links are how a crawler actually finds things and how it decides what matters,
so a URL that appears only in the sitemap is a URL we are asking to be indexed while giving no
reason to. That is the failure this exists to catch.

  python scripts/ci/link_audit.py [base_url] [--max N] [--json out.json]

Exits non-zero when it finds an orphan or a contradiction; broken links are reported and, being
frequently upstream of a fix rather than the fix, do not fail the run on their own.
"""
import json
import re
import sys
import time
import urllib.parse
import urllib.request
from collections import defaultdict

BASE = 'https://ruinmytrip.com'
MAX_PAGES = 900
UA = {'User-Agent': 'RuinMyTripLinkAudit/1.0 (internal link integrity check)'}

# Paths a crawler is not meant to walk, and neither are we: they are private, they are actions, or
# they are infinite. Excluding them here is not the same as them being excluded from the sitemap --
# that is checked separately and on purpose.
SKIP = re.compile(
    r'^/(admin|login|logout|register|forgot-password|reset-password|verify|settings|notifications'
    r'|messages|feed|contribute/|review/new|report|unsubscribe|suggest|api)(/|$|\?)')
SKIP_EXT = re.compile(r'\.(png|jpg|jpeg|gif|webp|svg|ico|css|js|xml|txt|pdf|zip)$', re.I)


def fetch(url):
    req = urllib.request.Request(url, headers=UA)
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            body = r.read().decode('utf-8', 'replace')
            return r.status, r.url, body
    except urllib.error.HTTPError as e:
        return e.code, url, ''
    except Exception as e:
        return 0, url, str(e)


def normalise(href, page):
    """Absolute, fragment-free, and with the query strings that only re-order a page dropped.

    ?sort= and ?type= produce the same set of entities in a different arrangement. Treating them as
    separate nodes would make the graph mostly noise and would hide real orphans among thousands of
    permutations -- which is also exactly why they must not be separate pages in the index.
    """
    u = urllib.parse.urljoin(page, href)
    p = urllib.parse.urlsplit(u)
    if p.scheme not in ('http', 'https'):
        return None
    if p.netloc != urllib.parse.urlsplit(BASE).netloc:
        return None
    q = urllib.parse.parse_qsl(p.query)
    q = [(k, v) for k, v in q if k not in ('sort', 'page', 'src', 'return', 'q', 'type', 'price')]
    path = p.path.rstrip('/') or '/'
    return urllib.parse.urlunsplit(('https', p.netloc, path, urllib.parse.urlencode(q), ''))


def links_in(html, page):
    out = set()
    for m in re.finditer(r'<a\b[^>]*?href\s*=\s*["\']([^"\']+)["\']', html, re.I):
        u = normalise(m.group(1), page)
        if not u:
            continue
        path = urllib.parse.urlsplit(u).path
        if SKIP.match(path) or SKIP_EXT.search(path):
            continue
        out.add(u)
    return out


def meta(html, name):
    m = re.search(r'<meta\s+name=["\']%s["\']\s+content=["\']([^"\']*)["\']' % name, html, re.I)
    return m.group(1).strip() if m else None


def canonical_of(html):
    m = re.search(r'<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']+)["\']', html, re.I)
    return m.group(1).strip() if m else None


def crawl(base, max_pages):
    seen, queue = {}, [base + '/']
    inbound = defaultdict(set)
    while queue and len(seen) < max_pages:
        url = queue.pop(0)
        if url in seen:
            continue
        status, final, html = fetch(url)
        seen[url] = {
            'status': status,
            'redirected_to': final if final.rstrip('/') != url.rstrip('/') else None,
            'robots': meta(html, 'robots'),
            'canonical': canonical_of(html),
            'title': (re.search(r'<title>(.*?)</title>', html, re.S).group(1).strip()[:80]
                      if re.search(r'<title>(.*?)</title>', html, re.S) else None),
        }
        if status == 200 and html:
            for target in links_in(html, url):
                inbound[target].add(url)
                if target not in seen:
                    queue.append(target)
        time.sleep(0.05)
    return seen, inbound


def sitemap_urls(base):
    """Every URL the sitemap claims, following a sitemap index when there is one."""
    out = []
    status, _, body = fetch(base + '/sitemap.xml')
    if status != 200:
        return out
    children = re.findall(r'<sitemap>.*?<loc>(.*?)</loc>.*?</sitemap>', body, re.S)
    if children:
        for c in children:
            s2, _, b2 = fetch(c.strip())
            if s2 == 200:
                out += [u.strip() for u in re.findall(r'<url>.*?<loc>(.*?)</loc>', b2, re.S)]
        return out
    return [u.strip() for u in re.findall(r'<url>.*?<loc>(.*?)</loc>', body, re.S)]


def main():
    base = BASE
    max_pages = MAX_PAGES
    out_json = None
    args = sys.argv[1:]
    for i, a in enumerate(args):
        if a.startswith('http'):
            base = a.rstrip('/')
        elif a == '--max' and i + 1 < len(args):
            max_pages = int(args[i + 1])
        elif a == '--json' and i + 1 < len(args):
            out_json = args[i + 1]

    print('crawling %s' % base)
    seen, inbound = crawl(base, max_pages)
    smap = sitemap_urls(base)
    smap_norm = {normalise(u, base) for u in smap}
    smap_norm.discard(None)
    print('crawled %d pages, sitemap lists %d urls\n' % (len(seen), len(smap)))

    problems = 0

    # 1. Orphans: in the sitemap, but nothing on the site links to them.
    reached = {u for u, ins in inbound.items() if ins}
    orphans = sorted(u for u in smap_norm if u not in reached and u.rstrip('/') != base)
    if orphans:
        print('ORPHANS -- in the sitemap, reachable by no link (%d):' % len(orphans))
        for u in orphans[:40]:
            print('   ' + u)
        if len(orphans) > 40:
            print('   ... and %d more' % (len(orphans) - 40))
        problems += len(orphans)
    else:
        print('orphans: none')

    # 2. Contradictions: the sitemap and the page disagree about whether it should be indexed.
    noindexed = sorted(u for u in smap_norm
                       if seen.get(u, {}).get('robots') and 'noindex' in seen[u]['robots'].lower())
    if noindexed:
        print('\nNOINDEX BUT IN SITEMAP (%d):' % len(noindexed))
        for u in noindexed[:20]:
            print('   %s   [%s]' % (u, seen[u]['robots']))
        problems += len(noindexed)
    else:
        print('noindex-in-sitemap: none')

    # A canonical pointing elsewhere means the page is asking not to be the indexed one, so it has
    # no business being the URL we submitted.
    mismatched = []
    for u in sorted(smap_norm):
        c = seen.get(u, {}).get('canonical')
        if c and normalise(c, base) != u:
            mismatched.append((u, c))
    if mismatched:
        print('\nSITEMAP URL WHOSE CANONICAL POINTS ELSEWHERE (%d):' % len(mismatched))
        for u, c in mismatched[:20]:
            print('   %s\n      -> %s' % (u, c))
        problems += len(mismatched)
    else:
        print('canonical mismatches: none')

    # 3. Dead ends anywhere in the graph. Reported, not fatal.
    broken = sorted((u, d['status']) for u, d in seen.items() if d['status'] not in (200,))
    if broken:
        print('\nLINKED URLS NOT RETURNING 200 (%d):' % len(broken))
        for u, st in broken[:25]:
            src = sorted(inbound.get(u, []))[:1]
            print('   %s  [%s]  linked from %s' % (u, st, src[0] if src else '?'))
    else:
        print('broken links: none')

    redirects = sorted(u for u, d in seen.items() if d.get('redirected_to'))
    if redirects:
        print('\nLINKED URLS THAT REDIRECT (%d):' % len(redirects))
        for u in redirects[:15]:
            print('   %s -> %s' % (u, seen[u]['redirected_to']))

    # A crawled page nothing links to is a different thing from a sitemap orphan: it is reachable
    # only because we happened to start there. Informational.
    lonely = sorted(u for u in seen
                    if not inbound.get(u) and u.rstrip('/') != base and seen[u]['status'] == 200)
    if lonely:
        print('\nCRAWLED WITH NO INBOUND LINK (%d): %s' % (len(lonely), ', '.join(lonely[:5])))

    if out_json:
        json.dump({'pages': seen, 'inbound': {k: sorted(v) for k, v in inbound.items()},
                   'sitemap': sorted(smap_norm), 'orphans': orphans},
                  open(out_json, 'w', encoding='utf-8'), indent=1)
        print('\nwrote %s' % out_json)

    print('\n%s' % ('PROBLEMS: %d' % problems if problems else 'LINK GRAPH CLEAN'))
    return 1 if problems else 0


if __name__ == '__main__':
    sys.exit(main())
