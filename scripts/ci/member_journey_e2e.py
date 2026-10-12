"""A new member's whole first visit in a real browser (Playwright, headless), desktop and phone.

  M1  join with email: bad input is refused with a plain message and the email stays in the box,
      and mistakes never count toward the five accounts an hour limit
  M2  a good join creates the account, unconfirmed, and lands on the next step
  M3  a review written before confirming is held, not shown
  M4  the confirmation link: opening it consumes nothing, pressing Confirm does, the held review goes live
  M5  sign out, then a members page sends you to sign in and back again
  M6  a wrong password is refused; the right one works
  M7  profile: name, home city and bio saved and shown on the public profile
  M8  a confirmed member's review is published at once, kept, shown on the place page, the reviews
      page, the profile and its own page, and can be edited
  M9  review mistakes (no stars, too short) keep the text and say what is wrong
  M10 forgot password: a neutral answer, and the reset link sets a new password that signs in
  M11 Google sign in: the button leads to Google, or is hidden when it is not configured
  Every page: no horizontal scroll, no script errors, main buttons at least 40px tall on the phone.

usage: python scripts/ci/member_journey_e2e.py BASE DB_PATH
E2E_DESIGN=v2 runs it in the redesign. Writes only to the dev database it is pointed at.
"""
import os, sys, sqlite3, random, string, subprocess
from playwright.sync_api import sync_playwright

BASE, DB = sys.argv[1].rstrip('/'), sys.argv[2]
DESIGN = os.environ.get('E2E_DESIGN', '')
PHP = os.environ.get('PHP', r'C:\Users\BL\tools\php83\php.exe')
HELD = ('calouste-gulbenkian-museum-lisbon', 150)
LIVE = ('oceanario-de-lisboa-lisbon', 151)
BODY1 = 'Quiet galleries and a garden worth the visit alone, but the cafe queue at lunch eats half an hour.'
BODY2 = 'Huge central tank and the sunfish are the highlight, but weekend mornings are packed with school groups.'
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


def php(code):
    env = dict(os.environ, RMT_SQLITE=os.path.abspath(DB))
    return subprocess.run([PHP, '-c', 'php.local.ini', '-r', 'require "app/bootstrap.php"; ' + code],
                          capture_output=True, text=True, env=env).stdout.strip()


def run(p, dev, ctxargs):
    tag = lambda s: f'[{dev}] {s}'
    db('DELETE FROM rate_limits')   # earlier runs from 127.0.0.1 would trip the 5 joins an hour limit
    b = p.chromium.launch(headless=True)
    ctx = b.new_context(**ctxargs)
    if DESIGN:
        ctx.add_cookies([{'name': 'rmt_design', 'value': DESIGN, 'url': BASE}])
    page = ctx.new_page()
    errs = []
    page.on('pageerror', lambda e: errs.append(str(e)))
    phone = dev == 'mobile'

    def settle():
        try:
            page.wait_for_load_state('networkidle', timeout=8000)
        except Exception:
            page.wait_for_load_state('load')

    def go(path):
        page.goto(BASE + path); settle()
        check(tag('no horizontal scroll ' + path.split('?')[0]),
              page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))

    def submit(form_sel):
        page.locator(form_sel + ' button[type=submit], ' + form_sel + ' button:not([type])').first.click()
        page.wait_for_load_state('networkidle')

    def tall(sel, name):
        if phone and page.locator(sel).count():
            h = page.locator(sel).first.bounding_box()
            check(tag(name + ' is easy to tap'), h and h['height'] >= 40, h)

    stamp = ''.join(random.choices(string.ascii_lowercase, k=6))
    body1 = BODY1.replace('garden', 'garden (' + stamp + ')')
    body2 = BODY2.replace('sunfish', 'sunfish (' + stamp + ')')
    email = f'e2e_member_{dev}_{stamp}@example.com'
    pw = 'longenough123'

    # M1 bad input, checked by the server (browser checks switched off to reach it)
    go('/register')
    tall('form[action$="register"] button', 'join button')
    for label, em, pwd, age, want in [
        ('bad email', 'not-an-email', pw, True, 'valid email'),
        ('short password', email, 'short', True, '8 characters'),
        ('no age box', email, pw, False, '16'),
    ]:
        page.goto(BASE + '/register'); page.wait_for_load_state('networkidle')
        page.evaluate("document.querySelectorAll('form').forEach(f => f.noValidate = true)")
        page.fill('form[action$="register"] input[name=email]', em)
        page.fill('form[action$="register"] input[name=password]', pwd)
        if age:
            page.check('form[action$="register"] input[name=age_ok]')
        submit('form[action$="register"]')
        txt = page.inner_text('main') if page.locator('main').count() else page.inner_text('body')
        check(tag(f'M1 {label} is refused with a message'), want in txt and not db('SELECT 1 FROM users WHERE email = ?', (em,)), txt[:400].replace(chr(10), ' '))
        check(tag(f'M1 {label} keeps the email'), page.input_value('form[action$="register"] input[name=email]') == em)

    # M1b typing mistakes never lock a person out: four more bad presses (seven in all), then a good one
    for i in range(4):
        page.goto(BASE + '/register'); page.wait_for_load_state('networkidle')
        page.evaluate("document.querySelectorAll('form').forEach(f => f.noValidate = true)")
        page.fill('form[action$="register"] input[name=email]', 'typo%d@' % i)
        page.fill('form[action$="register"] input[name=password]', pw)
        page.check('form[action$="register"] input[name=age_ok]')
        submit('form[action$="register"]')
    check(tag('M1 seven mistakes in a row are not called too many accounts'), 'Too many' not in page.inner_text('body'))

    # M2 good join
    page.goto(BASE + '/register'); page.wait_for_load_state('networkidle')
    page.fill('form[action$="register"] input[name=email]', email)
    page.fill('form[action$="register"] input[name=password]', pw)
    page.check('form[action$="register"] input[name=age_ok]')
    submit('form[action$="register"]')
    row = db('SELECT id, username, email_verified_at, status FROM users WHERE email = ?', (email,))
    check(tag('M2 the account is created'), len(row) == 1 and row[0][3] == 'active', row)
    uid, uname = row[0][0], row[0][1]
    check(tag('M2 it starts unconfirmed'), row[0][2] is None)
    check(tag('M2 it lands on the next step'), '/plan' in page.url, page.url)
    check(tag('M2 it says to check the email'), 'confirm' in page.inner_text('body').lower())
    go('/register')
    check(tag('M2 a signed in member is not shown the join form again'), '/register' not in page.url, page.url)

    # M2b the same email again is refused (fresh browser, signed out)
    c2 = b.new_context(**ctxargs); p2 = c2.new_page()
    p2.goto(BASE + '/register'); p2.wait_for_load_state('networkidle')
    p2.fill('form[action$="register"] input[name=email]', email.upper())
    p2.fill('form[action$="register"] input[name=password]', pw)
    p2.check('form[action$="register"] input[name=age_ok]')
    p2.locator('form[action$="register"] button').first.click(); p2.wait_for_load_state('networkidle')
    check(tag('M2 the same email (any case) cannot join twice'),
          len(db('SELECT 1 FROM users WHERE lower(email) = ?', (email,))) == 1 and 'already' in p2.inner_text('body'),
          p2.inner_text('body')[:200])
    c2.close()

    # M3 held review
    go(f'/review/new?place={HELD[1]}&src=place')
    page.click('label[for=rate-4]')
    page.fill('textarea[name=body]', body1)
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    r = db('SELECT id, status, held_for_verification FROM reviews WHERE user_id = ? AND place_id = ?', (uid, HELD[1]))
    check(tag('M3 the review is kept'), len(r) == 1, (r, page.url))
    check(tag('M3 it waits for the email to be confirmed'), bool(r) and r[0][2] == 1, r)
    page.goto(f'{BASE}/p/{HELD[0]}'); page.wait_for_load_state('networkidle')
    check(tag('M3 it is not on the place page yet'), 'garden (' + stamp + ')' not in page.content())

    # M4 confirmation link
    token = php('echo rmt_token_issue(%d, "verify");' % uid)
    go('/verify-email?token=' + token)
    check(tag('M4 opening the link does not confirm by itself'), db('SELECT email_verified_at FROM users WHERE id = ?', (uid,))[0][0] is None)
    tall('form[action$="verify-email/confirm"] button', 'confirm button')
    submit('form[action$="verify-email/confirm"]')
    check(tag('M4 pressing Confirm confirms'), db('SELECT email_verified_at FROM users WHERE id = ?', (uid,))[0][0] is not None)
    r = db('SELECT status, held_for_verification FROM reviews WHERE user_id = ? AND place_id = ?', (uid, HELD[1]))
    check(tag('M4 the held review is now published'), r and r[0][0] == 'published' and r[0][1] == 0, r)
    page.goto(f'{BASE}/p/{HELD[0]}'); page.wait_for_load_state('networkidle')
    check(tag('M4 and shown on the place page'), 'garden (' + stamp + ')' in page.content())
    go('/verify-email?token=' + token)
    check(tag('M4 a used link says so plainly'), 'already been used' in page.inner_text('body'))

    # M5 sign out, protected page, sign back in to where you were going
    go('/logout')
    check(tag('M5 signed out'), page.locator('a[href$="/logout"]').count() == 0)
    go(f'/u/{uname}/edit')
    check(tag('M5 a members page asks you to sign in'), '/login' in page.url, page.url)
    # M6 wrong then right password
    page.fill('form[action$="login"] input[name=email]', email)
    page.fill('form[action$="login"] input[name=password]', 'wrongpassword1')
    submit('form[action$="login"]')
    check(tag('M6 a wrong password is refused'), 'Incorrect email or password' in page.inner_text('body'))
    tall('form[action$="login"] button', 'sign in button')
    page.fill('form[action$="login"] input[name=email]', email)
    page.fill('form[action$="login"] input[name=password]', pw)
    submit('form[action$="login"]')
    check(tag('M6 the right one signs in and returns to the page you wanted'), f'/u/{uname}/edit' in page.url, page.url)

    # M7 profile
    page.fill('input[name=display_name]', 'E2E Test Member')
    if page.locator('input[name=home_city]').count():
        page.fill('input[name=home_city]', 'Porto')
    page.fill('textarea[name=bio]', 'Test profile written by the automated check.')
    page.locator('form[action$="/edit"] button[type=submit], form[action$="/edit"] button:not([type])').last.click()
    page.wait_for_load_state('networkidle')
    go(f'/u/{uname}')
    body = page.inner_text('body')
    check(tag('M7 the profile shows the new name and bio'), 'E2E Test Member' in body and 'automated check' in body, body[:300])

    # M8 confirmed member's review
    go(f'/review/new?place={LIVE[1]}&src=place')
    tall('button[name=action][value=publish]', 'publish button')
    # M9 first: mistakes
    page.evaluate("document.querySelectorAll('form').forEach(f => f.noValidate = true)")
    page.fill('textarea[name=body]', body2)
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    t = page.inner_text('body')
    check(tag('M9 no stars is refused with a message and the text stays'),
          not db('SELECT 1 FROM reviews WHERE user_id = ? AND place_id = ?', (uid, LIVE[1])) and body2[:30] in page.content(), t[:200])
    page.evaluate("document.querySelectorAll('form').forEach(f => f.noValidate = true)")
    page.click('label[for=rate-4]')
    page.fill('textarea[name=body]', 'Too short')
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    check(tag('M9 too short is refused and the text stays'),
          not db('SELECT 1 FROM reviews WHERE user_id = ? AND place_id = ?', (uid, LIVE[1])) and 'Too short' in page.content())
    page.click('label[for=rate-4]')
    page.fill('textarea[name=body]', body2)
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    r = db('SELECT id, slug, status, held_for_verification, rating FROM reviews WHERE user_id = ? AND place_id = ?', (uid, LIVE[1]))
    check(tag('M8 a confirmed member publishes at once'), r and r[0][2] == 'published' and r[0][3] == 0 and r[0][4] == 4, (r, page.url))
    rid = r[0][0] if r else 0
    check(tag('M8 it lands on the review'), f'/review/{rid}' in page.url, page.url)
    for path, name in [(f'/p/{LIVE[0]}', 'place page'), ('/reviews', 'reviews page'), (f'/u/{uname}', 'profile'), (f'/review/{rid}', 'its own page')]:
        go(path)
        check(tag('M8 shown on the ' + name), 'sunfish (' + stamp + ')' in page.content())
    go(f'/review/{rid}/edit')
    page.fill('textarea[name=body]', body2 + ' Go after 3pm.')
    page.click('button[name=action][value=publish]'); page.wait_for_load_state('networkidle')
    check(tag('M8 an edit is saved'), 'Go after 3pm' in (db('SELECT body FROM reviews WHERE id = ?', (rid,)) or [['']])[0][0])
    # persistence across a new browser session
    c3 = b.new_context(**ctxargs); p3 = c3.new_page()
    p3.goto(f'{BASE}/review/{rid}'); p3.wait_for_load_state('networkidle')
    check(tag('M8 a stranger sees the edited review'), 'Go after 3pm' in p3.content())
    c3.close()

    # M10 forgot password
    go('/logout')
    go('/forgot-password')
    page.fill('form[action$="forgot-password"] input[name=email]', email)
    submit('form[action$="forgot-password"]')
    known = page.inner_text('body')
    page.goto(BASE + '/forgot-password'); page.wait_for_load_state('networkidle')
    page.fill('form[action$="forgot-password"] input[name=email]', 'nobody_' + stamp + '@example.com')
    submit('form[action$="forgot-password"]')
    check(tag('M10 the answer does not reveal who has an account'), known == page.inner_text('body'))
    rt = php('echo rmt_token_issue(%d, "reset");' % uid)
    go('/reset-password?token=' + rt)
    page.fill('input[name=password]', pw + 'X'); page.fill('input[name=password_confirm]', pw + 'X')
    submit('form[action$="reset-password"]')
    go('/logout')
    go('/login')
    page.fill('form[action$="login"] input[name=email]', email)
    page.fill('form[action$="login"] input[name=password]', pw + 'X')
    submit('form[action$="login"]')
    check(tag('M10 the new password signs in'), '/login' not in page.url, page.url)

    # M11 Google
    go('/logout'); go('/login')
    g = page.locator('a[href*="auth/google"]')
    configured = php('echo (getenv("GOOGLE_CLIENT_ID") ?: (cfg("google_client_id") ?? "")) !== "" ? "1" : "0";') == '1'
    if g.count():
        r = page.request.get(BASE + '/auth/google', max_redirects=0)
        loc = r.headers.get('location', '')
        check(tag('M11 the Google button leads to Google'), 'accounts.google.com' in loc or not configured, (r.status, loc[:120]))
    else:
        check(tag('M11 no Google button when it is not configured'), not configured)

    check(tag('no script errors on any page'), not errs, errs[:3])
    b.close()


with sync_playwright() as p:
    run(p, 'desktop', dict(viewport={'width': 1366, 'height': 900}))
    run(p, 'mobile', dict(**p.devices['iPhone 13']))
print(f'\nmember_journey_e2e{" (" + DESIGN + ")" if DESIGN else ""}: {len(fails)} failed')
sys.exit(1 if fails else 0)
