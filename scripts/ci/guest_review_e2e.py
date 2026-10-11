"""Guest review journeys in a real browser (Playwright, headless), desktop and phone.

Every way a visitor's review used to be lost, replayed against a local server:

  J1  place page -> star -> write -> post -> join page shows it -> create account -> held review saved
  J2  the session cookie is lost after posting: /plan/join still shows the review (server copy)
  J3  the form's security token expires while typing: the form comes back with the text, no 403
  J4  too short: a plain message, the text stays in the box
  J5  coming back to the place's form later: the review is put back in it
  J6  account step fails (password too short): the review is still on the page
  J7  an existing member signs in from the join step: the review is saved to their account
  J8  the browser's own copy (localStorage) is cleared only after the server has the review

usage: python scripts/ci/guest_review_e2e.py BASE DB_PATH [shots_dir]
Exits non-zero on any failure. Writes nothing outside the dev database it is pointed at.
"""
import sys, sqlite3, time, json, random, string
from playwright.sync_api import sync_playwright

BASE, DB = sys.argv[1].rstrip('/'), sys.argv[2]
SHOTS = sys.argv[3] if len(sys.argv) > 3 else None
PLACE_SLUG, PLACE_ID = 'a-brasileira-lisbon', 39
BODY = 'Great coffee and a proper pastel de nata, but the terrace is a tourist trap at noon and costs double.'
fails = []


def check(name, cond, detail=''):
    print(('  PASS  ' if cond else '  FAIL  ') + name + (('  -- ' + str(detail)) if (detail and not cond) else ''))
    if not cond:
        fails.append(name)


def db(sql, args=()):
    c = sqlite3.connect(DB)
    try:
        return c.execute(sql, args).fetchall()
    finally:
        c.close()


def shot(page, name):
    if SHOTS:
        page.screenshot(path=f'{SHOTS}/{name}.png', full_page=True)


def open_form(page):
    page.goto(f'{BASE}/p/{PLACE_SLUG}')
    page.locator('.rate-stars a').first.click()          # the first star in DOM order is 5 stars
    page.wait_for_url('**/review/new**')


def write_and_post(page, body=BODY):
    page.fill('textarea[name=body]', body)
    page.click('button[name=action][value=publish]')
    page.wait_for_load_state('networkidle')


def run(p, dev, ctxargs):
    b = p.chromium.launch(headless=True)
    tag = lambda s: f'[{dev}] {s}'
    stamp = ''.join(random.choices(string.ascii_lowercase, k=6))

    # J1 + J8
    ctx = b.new_context(**ctxargs); page = ctx.new_page()
    errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
    open_form(page)
    check(tag('J1 the star opens the form with 5 stars chosen'), page.is_checked('#rate-5'))
    check(tag('J1 no horizontal scroll on the form'), page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    check(tag('J1 optional fields are tucked away'), not page.is_visible('#what_ruined') and not page.is_visible('#visited_on'))
    page.fill('textarea[name=body]', 'Too short')
    check(tag('J1 the counter says how far to go'), 'of 40' in page.inner_text('#body-count'))
    page.fill('textarea[name=body]', BODY)
    check(tag('J1 the counter says it is enough'), 'Good to go' in page.inner_text('#body-count'))
    shot(page, f'after_{dev}_form')
    page.wait_for_timeout(900)                            # review-draft.js debounce
    key = 'rmt.draft.new-%d-' % PLACE_ID
    has_local = lambda: page.evaluate("Object.keys(localStorage).some(k => k.indexOf('%s') === 0)" % key)
    check(tag('J8 the browser keeps a copy while typing'), has_local())
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    check(tag('J1 posting goes to the account step'), page.url.endswith('/plan/join'), page.url)
    check(tag('J1 the account step shows the review'), 'pastel de nata' in page.content() or 'tourist trap' in page.content())
    check(tag('J8 the browser copy is cleared once the server has it'), not has_local())
    check(tag('J1 the server copy exists'), any(c['name'] == 'rmt_rdraft' for c in ctx.cookies()))
    shot(page, f'after_{dev}_join')
    email = f'e2e_{dev}_{stamp}@example.com'
    page.fill('input[name=email]', email); page.fill('input[name=password]', 'longenough123')
    page.check('input[name=age_ok]')
    page.click('form[action$="plan/join"] button'); page.wait_for_load_state('networkidle')
    rows = db("SELECT r.status, r.held_for_verification, r.body FROM reviews r JOIN users u ON u.id = r.user_id WHERE u.email = ?", (email,))
    check(tag('J1 the account was made and the review saved to it'), len(rows) == 1 and 'pastel de nata' in rows[0][2], rows)
    check(tag('J1 it waits for the email to be confirmed'), bool(rows) and rows[0][1] == 1, rows)
    check(tag('J1 the server copy is deleted once saved'), not any(c['name'] == 'rmt_rdraft' and c['value'] for c in ctx.cookies()))
    check(tag('J1 no script errors'), not errors, errors)
    ctx.close()

    # J2 session lost after posting
    ctx = b.new_context(**ctxargs); page = ctx.new_page()
    open_form(page); write_and_post(page)
    keep = [c for c in ctx.cookies() if c['name'] != 'rmt_sess']
    ctx.clear_cookies(); ctx.add_cookies(keep)
    page.goto(f'{BASE}/plan/join')
    check(tag('J2 a lost session does not lose the review'), page.url.endswith('/plan/join') and 'tourist trap' in page.content(), page.url)
    # J5 the place form brings it back
    page.goto(f'{BASE}/review/new?place={PLACE_ID}&src=place')
    check(tag('J5 the form for the same place is refilled'), page.input_value('textarea[name=body]') == BODY)
    check(tag('J5 and says so'), page.is_visible('.review-saved-note'))
    ctx.close()

    # J3 token expires while typing
    ctx = b.new_context(**ctxargs); page = ctx.new_page()
    open_form(page)
    page.fill('textarea[name=body]', BODY)
    keep = [c for c in ctx.cookies() if c['name'] != 'rmt_sess']
    ctx.clear_cookies(); ctx.add_cookies(keep)               # the session behind the form is gone
    resp = []
    page.on('response', lambda r: resp.append(r.status) if r.request.method == 'POST' else None)
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    check(tag('J3 no 403'), 403 not in resp, resp)
    check(tag('J3 the form comes back with the text'), page.input_value('textarea[name=body]') == BODY)
    check(tag('J3 and says what happened'), 'security check' in page.content() or 'cookie' in page.content())
    shot(page, f'after_{dev}_expired')
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    check(tag('J3 the second press goes through'), page.url.endswith('/plan/join'), page.url)
    ctx.close()

    # J4 too short, J6 account step failure
    ctx = b.new_context(**ctxargs); page = ctx.new_page()
    open_form(page)
    page.evaluate("document.querySelector('textarea[name=body]').removeAttribute('minlength')")
    write_and_post(page, 'Too short')
    check(tag('J4 a short review is refused in plain words'), 'at least 40 characters' in page.content())
    check(tag('J4 and the text is still there'), page.input_value('textarea[name=body]') == 'Too short')
    write_and_post(page)
    page.fill('input[name=email]', f'e2e_fail_{dev}_{stamp}@example.com'); page.fill('input[name=password]', 'short')
    page.check('input[name=age_ok]')
    page.evaluate("document.querySelector('input[name=password]').removeAttribute('minlength')")
    page.click('form[action$="plan/join"] button'); page.wait_for_load_state('networkidle')
    check(tag('J6 a failed account step keeps the review on the page'), 'tourist trap' in page.content())
    ctx.close()

    # J7 an existing member signs in from the join step
    ctx = b.new_context(**ctxargs); page = ctx.new_page()
    before = db("SELECT COUNT(*) FROM reviews WHERE user_id = 1")[0][0]
    open_form(page); write_and_post(page, BODY + ' ' + stamp)
    page.click('a[href*="login?return="]'); page.wait_for_load_state('networkidle')
    page.fill('input[name=email]', 'maya@example.com'); page.fill('input[name=password]', 'travel1234')
    page.locator('form[action$="login"] button[type=submit], form[action*="login"] button').first.click()
    page.wait_for_load_state('networkidle')
    after = db("SELECT COUNT(*) FROM reviews WHERE user_id = 1")[0][0]
    check(tag('J7 signing in saves the review to the account'), after == before + 1, (before, after, page.url))
    check(tag('J7 it lands on the published review'), '/review/' in page.url, page.url)
    ctx.close()
    b.close()


with sync_playwright() as p:
    run(p, 'desktop', dict(viewport={'width': 1366, 'height': 900}))
    run(p, 'mobile', dict(**p.devices['iPhone 13']))

print('\nguest_review_e2e: ' + ('all passed' if not fails else f'{len(fails)} FAILED'))
sys.exit(1 if fails else 0)
