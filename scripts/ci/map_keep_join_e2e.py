"""A signed out travel map kept through joining, end to end in a browser (2026-10-12).

  python scripts/ci/map_keep_join_e2e.py BASE DB_PATH [SHOTS_DIR]
  E2E_DESIGN=v2 for the redesign.

Before: "Keep it on a free profile" sent the reader to join and back to the map with nothing kept.
Now the countries wait in the session and are saved when they come back signed in, then the reader
lands on their profile map. Someone who signs in to an existing account instead keeps what they had
and gains the new countries. Desktop (a real Chrome agent; the crawler filter drops HeadlessChrome
before any event is written) and an iPhone 13.
"""
import os, sys, sqlite3, time
from playwright.sync_api import sync_playwright

BASE = sys.argv[1].rstrip('/')
DB = sys.argv[2]
SHOTS = sys.argv[3] if len(sys.argv) > 3 else ''
V2 = os.environ.get('E2E_DESIGN') == 'v2'
CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'
PW = 'correct-horse-battery-9'
fails = []


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


def saved(email):
    return sorted(r[0] for r in db('SELECT uc.country FROM user_countries uc JOIN users u ON u.id = uc.user_id WHERE u.email = ?', (email,)))


def context(p, dev):
    b = p.chromium.launch(headless=True)
    args = {'viewport': {'width': 1366, 'height': 900}, 'user_agent': CHROME} if dev == 'desktop' else dict(p.devices['iPhone 13'])
    ctx = b.new_context(**args)
    if V2:
        ctx.add_cookies([{'name': 'rmt_design', 'value': 'v2', 'url': BASE}])
    page = ctx.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    # The map rewrites the address bar to /map?c=... once drawn, so where the server sent the
    # reader is read from the navigations, not from page.url.
    page.visited = []
    page.on('framenavigated', lambda f: f == page.main_frame and page.visited.append(f.url))
    return b, page, errors


def no_scroll(page):
    return page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')


def join(page, email):
    f = 'form[action$="register"] '
    page.fill(f + 'input[name=email]', email)
    page.fill(f + 'input[name=password]', PW)
    page.check(f + 'input[name=age_ok]')
    page.locator(f + 'button').first.click(); page.wait_for_load_state('networkidle')


def run_new(p, dev):
    tag = lambda s: f'[{dev} new] {s}'
    db('DELETE FROM rate_limits')
    b, page, errors = context(p, dev)
    email = f'e2e.mapkeep.{dev}.{int(time.time())}@example.test'
    page.goto(f'{BASE}/map?c=620-484'); page.wait_for_load_state('networkidle')
    if SHOTS:
        page.screenshot(path=os.path.join(SHOTS, f'{"v2" if V2 else "v1"}_{dev}_1_map.png'))
    page.click('button[data-cta=map_save]'); page.wait_for_load_state('networkidle')
    check(tag('keep sends a signed out reader to join'), '/register' in page.url, page.url)
    check(tag('and back to the same map afterwards'), 'map%3Fc%3D484-620' in page.url, page.url)
    check(tag('the join page says why'), 'travel map' in page.inner_text('body').lower())
    check(tag('no sideways scroll on join'), no_scroll(page))
    since = time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(time.time() - 2))
    page.visited.clear()
    join(page, email)
    check(tag('after joining it lands on the profile map'), any('/u/' in u and u.endswith('/map') for u in page.visited), page.visited)
    check(tag('it says the map was saved'), 'Saved. Your map is on your profile' in page.inner_text('body'))
    check(tag('both countries are kept'), saved(email) == ['484', '620'], saved(email))
    check(tag('no sideways scroll on the profile map'), no_scroll(page))
    if SHOTS:
        page.screenshot(path=os.path.join(SHOTS, f'{"v2" if V2 else "v1"}_{dev}_2_saved.png'))
    time.sleep(1.0)
    ev = db("SELECT COUNT(*) FROM contribution_events WHERE event = 'map_saved' AND source = 'map_join' AND created_at >= ?", (since,))
    check(tag('recorded as map_saved from map_join'), ev[0][0] >= 1, ev)
    page.visited.clear()
    page.goto(f'{BASE}/map'); page.wait_for_load_state('networkidle')
    check(tag('a second visit is the plain map, nothing saved again'), not any('/u/' in u for u in page.visited), page.visited)
    check(tag('no script errors'), not errors, errors)
    b.close()
    return email


def run_existing(p, email):
    """An existing member, signed out, keeps a new map and signs in instead of joining."""
    tag = lambda s: f'[existing] {s}'
    db('DELETE FROM rate_limits')
    b, page, errors = context(p, 'desktop')
    page.goto(f'{BASE}/map?c=250'); page.wait_for_load_state('networkidle')
    page.click('button[data-cta=map_save]'); page.wait_for_load_state('networkidle')
    ret = page.url.split('return=', 1)[1] if 'return=' in page.url else ''
    page.goto(f'{BASE}/login?return={ret}'); page.wait_for_load_state('networkidle')
    page.fill('form[action$="login"] input[name=email]', email)
    page.fill('form[action$="login"] input[name=password]', PW)
    page.visited.clear()
    page.locator('form[action$="login"] button').first.click(); page.wait_for_load_state('networkidle')
    check(tag('signing in lands on the profile map'), any('/u/' in u and u.endswith('/map') for u in page.visited), page.visited)
    check(tag('the earlier countries are still there and France is added'), saved(email) == ['250', '484', '620'], saved(email))
    check(tag('no script errors'), not errors, errors)
    b.close()


def run_empty(p):
    """Keep with nothing coloured in leaves nothing waiting."""
    db('DELETE FROM rate_limits')
    b, page, errors = context(p, 'desktop')
    email = f'e2e.mapempty.{int(time.time())}@example.test'
    page.goto(f'{BASE}/map'); page.wait_for_load_state('networkidle')
    page.click('button[data-cta=map_save]'); page.wait_for_load_state('networkidle')
    join(page, email)
    check('[empty] an empty map saves nothing', saved(email) == [], saved(email))
    b.close()


with sync_playwright() as p:
    first = run_new(p, 'desktop')
    run_new(p, 'phone')
    run_existing(p, first)
    run_empty(p)
print(f'\n{"FAIL" if fails else "PASS"}: map_keep_join e2e ({"v2" if V2 else "current"}), {len(fails)} failed')
sys.exit(1 if fails else 0)
