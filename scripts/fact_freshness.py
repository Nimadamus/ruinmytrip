"""Are the prices and rules we publish still what the official page says?

Every editorial fact in database/editorial/*.json carries the official URL it came from and the
exact text (assert_text) that proved it. This fetches each URL and checks that text is still there.
A missing assert_text does not prove the fact is wrong (pages get redesigned), but it is the list a
person re-checks first. Prices go stale quietly: on 2026-11-21 the Vasa Museum (240 -> 195 SEK off
season), Book of Kells (26 -> from 21.50 euros) and WWII Museum pages were all out of date.

  python scripts/fact_freshness.py [--out report.md]

Read only. 4 fetches at a time, browser user agent, 25 s timeout.
"""
import argparse
import concurrent.futures as cf
import glob
import html
import json
import os
import re
import subprocess
import sys
import urllib.request

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'


def norm(t):
    t = html.unescape(re.sub(r'(?s)<script.*?</script>|<style.*?</style>|<[^>]+>', ' ', t))
    t = t.replace('–', '-').replace('—', '-').replace('\xa0', ' ')
    return re.sub(r'\s+', ' ', t).lower()


def nums_present(at, text):
    """Wording changed but every number in the proof text is still on the page: probably fine."""
    nums = [re.sub(r'[ .,]', '', n) for n in re.findall(r'\d[\d ., ]*\d|\d', at)]
    nums = [n for n in nums if n]
    flat = re.sub(r'(?<=\d)[ .,](?=\d)', '', text)
    return bool(nums) and all(re.search(r'(?<!\d)' + re.escape(n) + r'(?!\d)', flat) for n in nums)


def facts():
    out = []
    for f in sorted(glob.glob(os.path.join(REPO, 'database', 'editorial', '*.json'))):
        try:
            d = json.load(open(f, encoding='utf-8'))
        except Exception:  # noqa: BLE001
            continue

        def walk(o, owner):
            if isinstance(o, dict):
                name = o.get('name') or o.get('slug') or owner
                for fc in o.get('facts_checked') or []:
                    if isinstance(fc, dict) and fc.get('url') and fc.get('assert_text'):
                        out.append((os.path.basename(f), str(name), fc['fact'], fc['url'], fc['assert_text']))
                for v in o.values():
                    if isinstance(v, (dict, list)):
                        walk(v, name)
            elif isinstance(o, list):
                for v in o:
                    walk(v, owner)
        walk(d, '')
    return out


_cache = {}


def page(url):
    """curl, not urllib: several museum sites 403 or loop on redirects for urllib but serve curl."""
    if url not in _cache:
        try:
            r = subprocess.run(['curl', '-sL', '--compressed', '-m', '30', '-A', UA, '-H', 'Accept-Language: en-US,en;q=0.9',
                                '-H', 'Accept: text/html,application/xhtml+xml', '-w', '\n%{http_code}', url],
                               capture_output=True, timeout=45, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
            body, _, code = r.stdout.decode('utf-8', 'replace').rpartition('\n')
            _cache[url] = (int(code or 0), norm(body))
        except Exception as e:  # noqa: BLE001
            _cache[url] = (0, str(e)[:80])
    return _cache[url]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', default=os.path.join(os.path.expanduser('~'), 'rmt_research', 'fact_freshness.md'))
    a = ap.parse_args()
    fs = facts()
    urls = sorted({f[3] for f in fs})
    with cf.ThreadPoolExecutor(4) as ex:
        list(ex.map(page, urls))
    ok, drift, missing, unreachable = [], [], [], []
    for src, name, fact, url, at in fs:
        code, text = _cache[url]
        if code != 200 or not text:
            unreachable.append((src, name, fact, url, code))
        elif norm(at) in text:
            ok.append(name)
        elif nums_present(at, text):
            drift.append((src, name, fact, url, at))
        else:
            missing.append((src, name, fact, url, at))
    L = [f'# Fact freshness: {len(fs)} facts, {len(urls)} pages', '',
         f'- still on the official page: {len(ok)}', f'- proof text missing (re-check these): {len(missing)}',
         f'- page unreachable (blocked, moved or down): {len(unreachable)}', '',
         '## Proof text missing', '', '| File | Item | Fact | Expected text | URL |', '|---|---|---|---|---|']
    L += [f'| {s} | {n} | {f} | {t} | {u} |' for s, n, f, u, t in missing]
    L += ['', '## Wording changed, numbers still there', '', '| File | Item | Fact | URL |', '|---|---|---|---|']
    L += [f'| {s} | {n} | {f} | {u} |' for s, n, f, u, t in drift]
    L += ['', '## Unreachable', '', '| File | Item | Fact | Status | URL |', '|---|---|---|---|---|']
    L += [f'| {s} | {n} | {f} | {c} | {u} |' for s, n, f, u, c in unreachable]
    os.makedirs(os.path.dirname(a.out), exist_ok=True)
    with open(a.out, 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L) + '\n')
    print(f'{len(fs)} facts: {len(ok)} ok, {len(drift)} drift, {len(missing)} missing, {len(unreachable)} unreachable -> {a.out}')


if __name__ == '__main__':
    main()
