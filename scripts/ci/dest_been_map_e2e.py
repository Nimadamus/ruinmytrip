"""City page "I've been" for a signed out reader, end to end in a browser (2026-10-12).

  python scripts/ci/dest_been_map_e2e.py BASE DB_PATH [SHOTS_DIR]
  E2E_DESIGN=v2 for the redesign.

A reader who reached a city page from search presses "I've been" and lands on the travel map with
that country already coloured in, with no account. The press is recorded as cta_click
dest_been_map. Desktop 1366 and an iPhone 13. Signed in, the old "I've been" form is still there
and the new button is not. Every page: no sideways scroll, no script errors, the button is tall
enough to tap.
"""
import os, sys, sqlite3, time
from playwright.sync_api import sync_playwright

BASE = sys.argv[1].rstrip('/')
DB = sys.argv[2]
SHOTS = sys.argv[3] if len(sys.argv) > 3 else ''
V2 = os.environ.get('E2E_DESIGN') == 'v2'
CITY, COUNTRY, CODE = 'lisbon-portugal', 'Portugal', '620'
fails = []
CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'


def check(name, cond, detail=''):
    print(('  PASS  ' if cond else '  FAIL  ') + name + (('  -- ' + str(detail)[:300]) if (detail and not cond) else ''))
    if not cond:
        fails.append(name)


def db(sql, args=()):
    c = sqlite3.connect(DB)
    try:
        rows = c.execute(sql, args).fetchall()
        c.commit()
        return rows
    finally:
        c.close()


def run(p, dev, ctxargs):
    tag = lambda s: f'[{dev}] {s}'
    b = p.chromium.launch(headless=True)
    ctx = b.new_context(**ctxargs)
    if V2:
        ctx.add_cookies([{'name': 'rmt_design', 'value': 'v2', 'url': BASE}])
    page = ctx.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))

    page.goto(f'{BASE}/d/{CITY}'); page.wait_for_load_state('networkidle')
    btn = page.locator('a[data-cta="dest_been_map"]')
    check(tag('signed out, the city page has the button'), btn.count() == 1, btn.count())
    if btn.count() != 1:
        b.close(); return
    check(tag('it names the country'), COUNTRY in btn.inner_text(), btn.inner_text())
    check(tag('it is nofollow'), 'nofollow' in (btn.get_attribute('rel') or ''))
    check(tag('the old join first link is gone'), page.locator('a:has-text("Join to mark been")').count() == 0)
    box = btn.bounding_box()
    check(tag('it is tall enough to tap'), box and box['height'] >= 36, box)
    check(tag('no sideways scroll on the city page'),
          page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))
    if SHOTS:
        btn.scroll_into_view_if_needed()
        page.screenshot(path=os.path.join(SHOTS, f'{"v2" if V2 else "v1"}_{dev}_city.png'))

    since = time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(time.time() - 5))
    btn.click(); page.wait_for_load_state('networkidle')
    check(tag('it opens the travel map'), '/map' in page.url, page.url)
    check(tag('with the country in the address'), f'c={CODE}' in page.url, page.url)
    codes = page.get_attribute('[data-tm-codes]', 'data-tm-codes')
    check(tag('the map starts with that country'), codes == CODE, codes)
    count = page.inner_text('[data-tm-count]') if page.locator('[data-tm-count]').count() else ''
    check(tag('the map says one country'), '1' in count, count)
    check(tag('no account was needed'), '/register' not in page.url and '/login' not in page.url)
    check(tag('no sideways scroll on the map'),
          page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))
    if SHOTS:
        page.screenshot(path=os.path.join(SHOTS, f'{"v2" if V2 else "v1"}_{dev}_map.png'))
    time.sleep(1.0)
    row = db("SELECT COUNT(*) FROM contribution_events WHERE event = 'cta_click' AND created_at >= ? AND detail = 'dest_been_map'", (since,))
    check(tag('the press is recorded as dest_been_map'), row[0][0] >= 1, row)
    check(tag('no script errors'), not errors, errors)
    b.close()


def run_member(p):
    """Signed in, the page keeps its own "I've been" form and does not show the map button."""
    db('DELETE FROM rate_limits')
    b = p.chromium.launch(headless=True)
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    if V2:
        ctx.add_cookies([{'name': 'rmt_design', 'value': 'v2', 'url': BASE}])
    page = ctx.new_page()
    email = f'e2e.been.{int(time.time())}@example.test'
    page.goto(BASE + '/register'); page.wait_for_load_state('networkidle')
    f = 'form[action$="register"] '
    page.fill(f + 'input[name=email]', email)
    page.fill(f + 'input[name=password]', 'correct-horse-battery-9')
    page.check(f + 'input[name=age_ok]')
    page.locator(f + 'button').first.click(); page.wait_for_load_state('networkidle')
    page.goto(f'{BASE}/d/{CITY}'); page.wait_for_load_state('networkidle')
    check('[member] signed in, no map button', page.locator('a[data-cta="dest_been_map"]').count() == 0)
    if not V2:
        check('[member] signed in, the "I have been" form is still there',
              page.locator('form[action$="destination/been"]').count() == 1)
    b.close()


with sync_playwright() as p:
    # A real Chrome agent: the headless default says HeadlessChrome, and the crawler filter rightly
    # drops every event from it before anything is written.
    run(p, 'desktop', {'viewport': {'width': 1366, 'height': 900}, 'user_agent': CHROME})
    run(p, 'phone', dict(p.devices['iPhone 13']))
    run_member(p)
print(f'\n{"FAIL" if fails else "PASS"}: dest_been_map e2e ({"v2" if V2 else "current"}), {len(fails)} failed')
sys.exit(1 if fails else 0)
