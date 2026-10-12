"""A shared travel map, end to end in a browser (2026-10-12).

  python scripts/ci/map_shared_view_e2e.py BASE DB_PATH [SHOTS_DIR]
  E2E_DESIGN=v2 for the redesign.

"Share my map" now sends /map?c=...&from=share. The friend who opens it sees the sharer's map, read
only ("They have been to N countries"), with "Make your own travel map", which opens an empty map of
their own. Before, the friend got "I've been to N countries" with someone else's countries in, theirs
to edit. A plain /map?c= link (the address bar, older shares) still opens editable. Desktop (a real
Chrome agent; the crawler filter drops HeadlessChrome before any event is written) and an iPhone 13.
"""
import os, sys, sqlite3, time
from playwright.sync_api import sync_playwright

BASE = sys.argv[1].rstrip('/')
DB = sys.argv[2]
SHOTS = sys.argv[3] if len(sys.argv) > 3 else ''
V2 = os.environ.get('E2E_DESIGN') == 'v2'
CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'
# Headless has no share sheet; this one keeps what would have been shared.
FAKE_SHARE = "navigator.share = function (d) { window.__shared = d; return Promise.resolve(); };"
fails = []


def check(name, cond, detail=''):
    print(('  PASS  ' if cond else '  FAIL  ') + name + (('  -- ' + str(detail)[:300]) if (detail and not cond) else ''))
    if not cond:
        fails.append(name)


def db(sql, args=()):
    c = sqlite3.connect(DB)
    try:
        return c.execute(sql, args).fetchall()
    finally:
        c.close()


def page_for(b, dev):
    args = {'viewport': {'width': 1366, 'height': 900}, 'user_agent': CHROME} if dev == 'desktop' else dict(p.devices['iPhone 13'])
    ctx = b.new_context(**args)
    if V2:
        ctx.add_cookies([{'name': 'rmt_design', 'value': 'v2', 'url': BASE}])
    ctx.add_init_script(FAKE_SHARE)
    page = ctx.new_page()
    page.errors = []
    page.on('pageerror', lambda e: page.errors.append(str(e)))
    return page


def no_scroll(page):
    return page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')


def run(b, dev):
    tag = lambda s: f'[{dev}] {s}'
    d = 'v2' if V2 else 'v1'
    sharer = page_for(b, dev)
    sharer.goto(f'{BASE}/map?c=620-484'); sharer.wait_for_load_state('networkidle')
    sharer.click('button[data-tm-share]')
    sharer.wait_for_function('window.__shared')
    url = sharer.evaluate('window.__shared.url')
    check(tag('the shared link carries the countries and from=share'), url.endswith('/map?c=484-620&from=share'), url)

    friend = page_for(b, dev)
    since = time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(time.time() - 2))
    friend.goto(url); friend.wait_for_load_state('networkidle')
    body = friend.inner_text('body')
    check(tag('the friend sees it as a shared map'), 'a shared travel map' in body.lower(), body[:200])
    check(tag('in the sharer\'s words, not their own'), 'They have been to 2 countries' in body and "I've been to" not in body, body[:300])
    check(tag('read only: no search, share or save'),
          friend.locator('[data-tm-find]').count() == 0 and friend.locator('[data-tm-share]').count() == 0
          and friend.locator('[data-tm-save-form]').count() == 0)
    check(tag('the map shows the sharer\'s countries'), friend.get_attribute('[data-tm-codes]', 'data-tm-codes') == '484-620')
    check(tag('canonical is still /map'), friend.get_attribute('link[rel=canonical]', 'href').endswith('/map'))
    check(tag('no sideways scroll'), no_scroll(friend))
    if SHOTS:
        friend.screenshot(path=os.path.join(SHOTS, f'{d}_{dev}_shared.png'))
    make = friend.locator('.tm-actions a[data-cta="map_make"]')
    check(tag('"Make your own travel map" is there'), make.count() == 1)
    make.click(); friend.wait_for_load_state('networkidle')
    check(tag('it opens an empty map of their own'), friend.get_attribute('[data-tm-codes]', 'data-tm-codes') == ''
          and friend.locator('[data-tm-find]').count() == 1, friend.url)
    if SHOTS:
        friend.screenshot(path=os.path.join(SHOTS, f'{d}_{dev}_own.png'))
    if dev == 'desktop':
        time.sleep(1.0)
        v = db("SELECT COUNT(*) FROM contribution_events WHERE event = 'map_view' AND source = 'share' AND created_at >= ?", (since,))
        check(tag('the visit is recorded as map_view from share'), v[0][0] >= 1, v)
        m = db("SELECT COUNT(*) FROM contribution_events WHERE event = 'cta_click' AND detail = 'map_make' AND created_at >= ?", (since,))
        check(tag('"make your own" is recorded'), m[0][0] >= 1, m)

    plain = page_for(b, dev)
    plain.goto(f'{BASE}/map?c=620'); plain.wait_for_load_state('networkidle')
    check(tag('a plain /map?c= link is still editable'), plain.locator('[data-tm-find]').count() == 1
          and "I've been to" in plain.inner_text('body'))
    check(tag('no script errors'), not (sharer.errors or friend.errors or plain.errors), sharer.errors + friend.errors + plain.errors)


with sync_playwright() as p:
    b = p.chromium.launch(headless=True)
    run(b, 'desktop')
    run(b, 'phone')
    b.close()
print(f'\n{"FAIL" if fails else "PASS"}: map_shared_view e2e ({"v2" if V2 else "current"}), {len(fails)} failed')
sys.exit(1 if fails else 0)
