"""From the travel map to a first review, end to end in a browser (2026-10-12).

  python scripts/ci/map_review_bridge_e2e.py BASE DB_PATH [SHOTS_DIR]
  E2E_DESIGN=v2 for the redesign.

Under the map, "Been to one of these cities?" lists the cities with a page here in the countries
coloured in, each "Review <city>", opening the review form with that city chosen. It follows the
map as countries are tapped, is not there on a shared map or somebody else's profile map, and an
empty map shows nothing. Desktop (a real Chrome agent; the crawler filter drops HeadlessChrome
before any event is written) and an iPhone 13.
"""
import os, sys, sqlite3, time
from playwright.sync_api import sync_playwright

BASE = sys.argv[1].rstrip('/')
DB = sys.argv[2]
SHOTS = sys.argv[3] if len(sys.argv) > 3 else ''
V2 = os.environ.get('E2E_DESIGN') == 'v2'
CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'
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
    page = ctx.new_page()
    page.errors = []
    page.on('pageerror', lambda e: page.errors.append(str(e)))
    return page


def cities(page):
    box = page.locator('[data-tm-review]')
    if box.count() == 0 or not box.is_visible():
        return []
    return [t.strip() for t in page.locator('[data-tm-review-list] a').all_inner_texts()]


def run(b, dev):
    tag = lambda s: f'[{dev}] {s}'
    d = 'v2' if V2 else 'v1'
    lisbon = db("SELECT id FROM destinations WHERE slug = 'lisbon-portugal'")[0][0]
    pt = sorted(r[0] for r in db("SELECT name FROM destinations WHERE country = 'Portugal'"))
    mx = sorted(r[0] for r in db("SELECT name FROM destinations WHERE country = 'Mexico'"))

    page = page_for(b, dev)
    page.goto(f'{BASE}/map'); page.wait_for_load_state('networkidle')
    check(tag('an empty map shows no cities'), cities(page) == [], cities(page))
    check(tag('an empty map has no city review links'), page.locator('a[data-cta="map_review"]').count() == 0)

    page.goto(f'{BASE}/map?c=620'); page.wait_for_load_state('networkidle')
    check(tag('Portugal lists its cities'), cities(page) == ['Review ' + n for n in pt][:12], (cities(page), pt))
    a = page.locator('[data-tm-review-list] a', has_text='Lisbon')
    href = a.get_attribute('href') or ''
    check(tag('each city starts a review of it, from the map'), href.endswith(f'/review/new?destination={lisbon}&src=map'), href)
    check(tag('the links are nofollow'), page.locator('[data-tm-review-list] a:not([rel="nofollow"])').count() == 0)
    check(tag('canonical is still /map'), page.get_attribute('link[rel=canonical]', 'href').endswith('/map'))

    page.fill('[data-tm-find]', 'Mexico'); page.press('[data-tm-find]', 'Enter'); page.wait_for_timeout(300)
    want = sorted(pt + mx)[:12]
    check(tag('tapping Mexico adds its cities, in name order'), cities(page) == ['Review ' + n for n in want], (cities(page), want))
    check(tag('no sideways scroll'), page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))
    if SHOTS:
        page.locator('[data-tm-review]').scroll_into_view_if_needed()
        page.screenshot(path=os.path.join(SHOTS, f'{d}_{dev}_map_review.png'))

    since = time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(time.time() - 2))
    page.locator('[data-tm-review-list] a', has_text='Lisbon').click(); page.wait_for_load_state('networkidle')
    check(tag('the review form opens'), '/review/new' in page.url, page.url)
    sel = page.locator('select#destination_id')
    check(tag('with Lisbon already chosen'), sel.count() == 1 and sel.input_value() == str(lisbon), sel.input_value() if sel.count() else 'no select')
    check(tag('signed out, the form is there to write first'), page.locator('textarea').count() >= 1)
    check(tag('no sideways scroll on the form'), page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))
    if SHOTS:
        page.screenshot(path=os.path.join(SHOTS, f'{d}_{dev}_review_form.png'))
    if dev == 'desktop':
        time.sleep(1.0)
        c = db("SELECT COUNT(*) FROM contribution_events WHERE event = 'cta_click' AND detail = 'map_review' AND created_at >= ?", (since,))
        check(tag('the press is recorded as cta map_review'), c[0][0] >= 1, c)
        f = db("SELECT COUNT(*) FROM contribution_events WHERE event = 'review_form_start' AND source = 'map' AND created_at >= ?", (since,))
        check(tag('the form start is recorded from the map'), f[0][0] >= 1, f)

    friend = page_for(b, dev)
    friend.goto(f'{BASE}/map?c=620&from=share'); friend.wait_for_load_state('networkidle')
    check(tag('a shared map does not offer the sharer\'s cities to review'), friend.locator('[data-tm-review]').count() == 0)
    check(tag('no script errors'), not (page.errors or friend.errors), page.errors + friend.errors)


with sync_playwright() as p:
    b = p.chromium.launch(headless=True)
    run(b, 'desktop')
    run(b, 'phone')
    b.close()
print(f'\n{"FAIL" if fails else "PASS"}: map_review_bridge e2e ({"v2" if V2 else "current"}), {len(fails)} failed')
sys.exit(1 if fails else 0)
