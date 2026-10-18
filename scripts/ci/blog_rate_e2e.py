"""Blog post -> "Been to <city>?" star -> review of the city -> account -> confirm -> published.
Headless Playwright, desktop and phone, against a local server (2026-10-17).

  B1  a blog post about a city shows the stars under the summary, linking to the city review form
  B2  the tapped star is chosen, the city, "destination" and its name are filled in
  B3  posting with only stars and a few sentences reaches the account step (no other box needed)
  B4  account made, confirming the email publishes it as a review of the city (no new place page)
  B5  a post with no city shows no stars; no horizontal scroll; no script errors

usage: python scripts/ci/blog_rate_e2e.py BASE DB_PATH
Exits non-zero on any failure. Writes nothing outside the dev database it is pointed at.
"""
import os, sys, random, string, sqlite3, subprocess, types
from playwright.sync_api import sync_playwright

BASE, DB = sys.argv[1].rstrip('/'), sys.argv[2]
g = types.SimpleNamespace(fails=[])   # same helpers as guest_review_e2e.py, which runs on import


def _check(name, cond, detail=''):
    print(('  PASS  ' if cond else '  FAIL  ') + name + (('  -- ' + str(detail)) if (detail and not cond) else ''))
    if not cond:
        g.fails.append(name)


def _db(sql, args=()):
    c = sqlite3.connect(DB)
    try:
        return c.execute(sql, args).fetchall()
    finally:
        c.close()


def _verify_token(uid):
    php = os.environ.get('PHP', r'C:\Users\BL\tools\php83\php.exe')
    code = 'require "app/bootstrap.php"; echo rmt_token_issue(%d, "verify");' % uid
    env = dict(os.environ, RMT_SQLITE=os.path.abspath(DB))
    return subprocess.run([php, '-c', 'php.local.ini', '-r', code], capture_output=True, text=True, env=env).stdout.strip()


g.check, g.db, g.verify_token = _check, _db, _verify_token

BODY = 'The tax was added at check in, cash only, and nobody had mentioned it when we booked the room.'
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'


def blog_with_city():
    rows = g.db("SELECT b.slug, d.id, d.name FROM blog_posts b JOIN destinations d "
                "ON b.body LIKE '%/d/' || d.slug || '%' WHERE b.status = 'published' ORDER BY b.id LIMIT 1")
    return rows[0]


def run(p, dev, ctxargs):
    tag = lambda s: f'[{dev}] {s}'
    slug = blog_with_city()[0]
    b = p.chromium.launch(headless=True)
    ctx = b.new_context(**dict(ctxargs, user_agent=UA)); page = ctx.new_page()
    errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(f'{BASE}/blog/{slug}')
    stars = page.locator('.rate-stars a[data-review-cta=blog_rate]')
    g.check(tag('B1 the post shows five city stars'), stars.count() == 5)
    dest_id = int(stars.first.get_attribute('data-destination-id'))
    dest_name = g.db("SELECT name FROM destinations WHERE id = ?", (dest_id,))[0][0]
    top = page.evaluate("document.querySelector('.rate-place').getBoundingClientRect().top / window.innerHeight")
    print('  INFO  [%s] stars start at %.2f screens' % (dev, top))
    g.check(tag('B1 they are on the first screen'), top < 1.0, top)
    g.check(tag('B5 no horizontal scroll on the post'), page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    stars.nth(1).click()                                   # DOM order 5,4,3,2,1: second is 4 stars
    page.wait_for_url('**/review/new**')
    g.check(tag('B2 four stars chosen'), page.is_checked('#rate-4'))
    hid = lambda n: page.get_attribute('form input[type=hidden][name=%s]' % n, 'value')
    g.check(tag('B2 the city is chosen'), hid('destination_id') == str(dest_id), hid('destination_id'))
    g.check(tag('B2 reviewing the destination'), hid('subject_type') == 'destination')
    g.check(tag('B2 named after the city'), hid('subject_name') == dest_name, hid('subject_name'))
    g.check(tag('B2 the form says what is reviewed'), 'You are reviewing' in page.inner_text('form[data-review-draft]') and dest_name in page.inner_text('form[data-review-draft]'))
    box = page.evaluate("document.querySelector('textarea[name=body]').getBoundingClientRect().top / window.innerHeight")
    print('  INFO  [%s] review box starts at %.2f screens' % (dev, box))
    g.check(tag('B2 the review box is on the first screen'), box < 1.0, box)
    g.check(tag('B5 no horizontal scroll on the form'), page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    page.fill('textarea[name=body]', BODY)
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    g.check(tag('B3 posting goes to the account step'), page.url.endswith('/plan/join'), page.url)
    g.check(tag('B3 the account step shows the review'), 'cash only' in page.content())
    email = 'e2e_blog_%s_%s@example.com' % (dev, ''.join(random.choices(string.ascii_lowercase, k=6)))
    page.fill('input[name=email]', email); page.fill('input[name=password]', 'longenough123')
    page.check('input[name=age_ok]')
    page.click('form[action$="plan/join"] button'); page.wait_for_load_state('networkidle')
    uid = g.db("SELECT id FROM users WHERE email = ?", (email,))[0][0]
    page.goto(f'{BASE}/verify-email?token={g.verify_token(uid)}')
    page.click('form[action$="verify-email/confirm"] button'); page.wait_for_load_state('networkidle')
    rows = g.db("SELECT status, held_for_verification, destination_id, subject_type, subject_name, rating, place_id "
                "FROM reviews WHERE user_id = ?", (uid,))
    g.check(tag('B4 published as a 4 star review of the city, no place page'),
            rows == [('published', 0, dest_id, 'destination', dest_name, 4, None)], rows)
    g.check(tag('B4 it lands on the live review'), '/review/' in page.url, page.url)
    g.check(tag('B5 no script errors'), not errors, errors)
    ctx.close(); b.close()


if __name__ == '__main__':
    # Every journey makes an account from 127.0.0.1; the join limit (5 an hour) is for people.
    c = sqlite3.connect(DB); c.execute("DELETE FROM rate_limits WHERE bucket LIKE 'register_%'"); c.commit(); c.close()
    with sync_playwright() as p:
        run(p, 'desktop', dict(viewport={'width': 1366, 'height': 900}))
        run(p, 'mobile', dict(**p.devices['iPhone 13']))
    print('FAILED: %d' % len(g.fails) if g.fails else 'ALL PASS')
    sys.exit(1 if g.fails else 0)
