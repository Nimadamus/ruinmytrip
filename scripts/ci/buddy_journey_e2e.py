"""Two new members find each other as travel buddies, in a real browser (Playwright, headless).

  B1  a new member (no birthdate, which is every member who joined with the 16+ tick) pressing
      "find a travel buddy" is asked their date of birth once and lands back on the form
  B2  the buddy post is published and shows on its own page and in the buddy finder
  B3  a second new member puts a hand up: asked their age once, then the note is sent
  B4  the poster sees the note and accepts; nobody could message before that
  B5  both can message each other
  B6  blocking stops messages
  B7  the post can be reported, once
  B8  a 17 year old is told buddies are 18+, keeps the account, and cannot answer again
  B9  trip first, signed out, buddy box ticked: after the email is confirmed the age is asked and
      the buddy post goes live (it used to be dropped without a word)
  Every page: no horizontal scroll, no script errors.

usage: python scripts/ci/buddy_journey_e2e.py BASE DB_PATH
Runs twice: poster on a laptop with the other member on a phone, then the other way round.
Writes only to the dev database it is pointed at.
"""
import os, sys, sqlite3, random, string, subprocess, datetime as dt
from playwright.sync_api import sync_playwright

BASE, DB = sys.argv[1].rstrip('/'), sys.argv[2]
fails = []
PHP = os.environ.get('PHP', r'C:\Users\BL\tools\php83\php.exe')
DESK = {'viewport': {'width': 1280, 'height': 900}}
PHONE = {'viewport': {'width': 390, 'height': 844}, 'is_mobile': True, 'has_touch': True, 'device_scale_factor': 2}
# A real browser UA: the headless one is treated as a crawler and its events are dropped.
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'


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


class Member:
    def __init__(self, browser, ctxargs, label):
        self.ctx = browser.new_context(user_agent=UA, **ctxargs)
        self.page = self.ctx.new_page()
        self.errs = []
        self.page.on('pageerror', lambda e: self.errs.append(str(e)))
        # The block button asks "Block @name?"; a person says yes.
        self.page.on('dialog', lambda d: d.accept())
        self.label = label

    def settle(self):
        try:
            self.page.wait_for_load_state('networkidle', timeout=8000)
        except Exception:
            self.page.wait_for_load_state('load')

    def go(self, path):
        self.page.goto(BASE + path); self.settle()
        self.scroll_ok(path)

    def scroll_ok(self, what):
        check(f'[{self.label}] no horizontal scroll {what.split("?")[0]}',
              self.page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))

    def press(self, sel):
        self.page.locator(sel).first.click(); self.settle()

    def text(self):
        return self.page.inner_text('body')

    def join(self, stamp, role):
        email = f'e2e_buddy_{role}_{stamp}@example.com'
        self.go('/register')
        self.page.fill('form[action$="register"] input[name=email]', email)
        self.page.fill('form[action$="register"] input[name=password]', 'longenough123')
        self.page.check('form[action$="register"] input[name=age_ok]')
        self.press('form[action$="register"] button')
        row = db('SELECT id, username, birthdate FROM users WHERE email = ?', (email,))
        check(f'[{self.label}] {role} account created with no birthdate', len(row) == 1 and row[0][2] is None, row)
        # Confirming the address is covered by member_journey_e2e; here it is simply marked done.
        db('UPDATE users SET email_verified_at = ? WHERE id = ?', (dt.datetime.now().strftime('%Y-%m-%d %H:%M:%S'), row[0][0]))
        self.uid, self.username = row[0][0], row[0][1]
        return self

    def answer_age(self, dob):
        check(f'[{self.label}] asked for a date of birth', '/account/age' in self.page.url, self.page.url)
        self.scroll_ok('/account/age')
        self.page.fill('input[name=dob]', dob)
        self.press('form[action$="account/age"] button')


def journey(p, poster_dev, other_dev):
    b = p.chromium.launch(headless=True)
    stamp = ''.join(random.choices(string.ascii_lowercase, k=6))
    db('DELETE FROM rate_limits')
    dest = db("SELECT id, slug, name FROM destinations WHERE slug = 'lisbon-portugal'") or db('SELECT id, slug, name FROM destinations ORDER BY id LIMIT 1')
    dest_id, dest_slug, dest_name = dest[0]
    start = (dt.date.today() + dt.timedelta(days=40)).isoformat()
    end = (dt.date.today() + dt.timedelta(days=47)).isoformat()

    a = Member(b, poster_dev[1], f'{poster_dev[0]} poster').join(stamp, 'poster')
    o = Member(b, other_dev[1], f'{other_dev[0]} other').join(stamp, 'other')

    # B1 the poster is asked once and comes back to the form, city still chosen
    a.go(f'/buddies/new?dest={dest_slug}')
    a.answer_age('1991-04-02')
    check(f'[{a.label}] B1 back on the buddy form', '/buddies/new' in a.page.url and a.page.locator('select[name=destination_id]').count() == 1, a.page.url)
    check(f'[{a.label}] B1 the city is still chosen', a.page.input_value('select[name=destination_id]') == str(dest_id))
    check(f'[{a.label}] B1 birthdate stored', db('SELECT birthdate FROM users WHERE id = ?', (a.uid,))[0][0] == '1991-04-02')
    a.go('/account/age?return=/buddies')
    check(f'[{a.label}] B1 the age cannot be answered again', '/account/age' not in a.page.url, a.page.url)

    # B2 publish
    a.go(f'/buddies/new?dest={dest_slug}')
    pg = a.page
    pg.select_option('select[name=trip_type]', 'trip')
    pg.fill('input[name=date_from]', start); pg.fill('input[name=date_to]', end)
    desc = f'First time in {dest_name}, keen on long walks, food markets and a day trip. Ref {stamp}.'
    pg.fill('textarea[name=description]', desc)
    pg.check('input[name=safety_ack]')
    a.press('form[action$="buddies/new"] button[type=submit]')
    row = db('SELECT id, status FROM buddy_posts WHERE user_id = ? ORDER BY id DESC LIMIT 1', (a.uid,))
    check(f'[{a.label}] B2 the post is saved', len(row) == 1, row)
    bid = row[0][0] if row else 0
    check(f'[{a.label}] B2 lands on the post', f'/buddy/{bid}' in pg.url, pg.url)
    check(f'[{a.label}] B2 the post shows its words', stamp in a.text())
    o.go(f'/buddies?dest={dest_slug}')
    check(f'[{o.label}] B2 the post is in the buddy finder', o.page.locator(f'a[href*="/buddy/{bid}"]').count() >= 1)

    # B3 the other member: messaging is shut before acceptance
    o.go(f'/messages/{a.username}')
    o.page.fill('textarea[name=body]', 'hello before accept') if o.page.locator('textarea[name=body]').count() else None
    if o.page.locator('form.msg-composer button').count():
        o.press('form.msg-composer button')
    check(f'[{o.label}] B3 no message before acceptance', not db('SELECT 1 FROM messages WHERE sender_id = ?', (o.uid,)))
    o.go(f'/buddy/{bid}')
    o.press(f'form[action$="buddy/{bid}/interest"] button')
    o.answer_age('1993-11-20')
    check(f'[{o.label}] B3 back on the post', f'/buddy/{bid}' in o.page.url, o.page.url)
    o.page.fill('textarea[name=note]', f'Same week, happy to share a day trip ({stamp}).')
    o.press(f'form[action$="buddy/{bid}/interest"] button')
    check(f'[{o.label}] B3 told the note was sent', 'Sent.' in o.text(), o.text()[:300])
    check(f'[{o.label}] B3 interest saved', bool(db('SELECT 1 FROM buddy_interest WHERE post_id = ? AND user_id = ?', (bid, o.uid))))

    # B4 accept
    a.go(f'/buddy/{bid}')
    check(f'[{a.label}] B4 the poster sees the note', stamp in a.text())
    a.press(f'form[action$="buddy/{bid}/decide/{o.uid}"] button[value=accepted], form[action$="buddy/{bid}/decide/{o.uid}"] button')

    # B5 messages both ways
    o.go(f'/messages/{a.username}')
    o.page.fill('textarea[name=body]', f'Hi, thanks for accepting ({stamp})')
    o.press('form.msg-composer button')
    a.go(f'/messages/{o.username}')
    check(f'[{a.label}] B5 the poster receives the message', f'thanks for accepting ({stamp})' in a.text())
    a.page.fill('textarea[name=body]', 'Great, see you there')
    a.press('form.msg-composer button')
    o.go(f'/messages/{a.username}')
    check(f'[{o.label}] B5 the reply arrives', 'Great, see you there' in o.text())

    # B6 block
    o.page.locator('details.more-menu summary').first.click()
    o.press('button.more-menu-danger')
    check(f'[{o.label}] B6 block saved', bool(db('SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ?', (o.uid, a.uid))))
    before = db('SELECT COUNT(*) FROM messages WHERE sender_id = ?', (a.uid,))[0][0]
    a.go(f'/messages/{o.username}')
    if a.page.locator('textarea[name=body]').count():
        a.page.fill('textarea[name=body]', 'are you there?')
        a.press('form.msg-composer button')
    check(f'[{a.label}] B6 a blocked member cannot message', db('SELECT COUNT(*) FROM messages WHERE sender_id = ?', (a.uid,))[0][0] == before)

    # B7 report the post, once
    for n in (1, 2):
        o.go(f'/report?target_type=buddy&target_id={bid}')
        o.page.select_option('select[name=reason]', 'unsafe')
        o.press('form[action$="report"] button')
    cnt = db("SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND target_type = 'buddy' AND target_id = ?", (o.uid, bid))[0][0]
    check(f'[{o.label}] B7 one report filed, the second refused', cnt == 1, cnt)

    # B8 a 17 year old
    y = Member(b, other_dev[1], f'{other_dev[0]} minor').join(stamp, 'minor')
    y.go('/buddies/new')
    seventeen = (dt.date.today().replace(year=dt.date.today().year - 17) - dt.timedelta(days=3)).isoformat()
    y.answer_age(seventeen)
    check(f'[{y.label}] B8 told buddies are 18+', '18 and over' in y.text(), y.text()[:300])
    check(f'[{y.label}] B8 not on the buddy form', '/buddies/new' not in y.page.url, y.page.url)
    y.go('/account/age?return=/buddies/new')
    check(f'[{y.label}] B8 cannot answer again', '/account/age' not in y.page.url, y.page.url)
    y.go('/buddies/new')
    check(f'[{y.label}] B8 still kept out of posting', '/buddies/new' not in y.page.url, y.page.url)

    # B9 trip first, signed out, with the buddy box ticked
    t = Member(b, poster_dev[1], f'{poster_dev[0]} trip first')
    t.go(f'/plan?buddy=1&d={dest_slug}')
    tp = t.page
    tp.evaluate("document.querySelectorAll('details').forEach(d => d.open = true)")
    if tp.input_value('select[name=destination_id]') != str(dest_id):
        tp.select_option('select[name=destination_id]', str(dest_id))
    tp.fill('input[name=date_from]', start); tp.fill('input[name=date_to]', end)
    if not tp.is_checked('input[name=want_buddy]'):
        tp.check('input[name=want_buddy]')
    tp.fill('textarea[name=buddy_note]', f'Looking for someone to share walks and dinners, ref {stamp}9.')
    tp.check('input[name=safety_ack]')
    t.press('#plan-form button[type=submit], #plan-form button:not([type])')
    email = f'e2e_buddy_tripfirst_{stamp}@example.com'
    check(f'[{t.label}] B9 the join step', '/plan/join' in tp.url, tp.url)
    tp.fill('form[action$="plan/join"] input[name=email]', email)
    tp.fill('form[action$="plan/join"] input[name=password]', 'longenough123')
    if tp.locator('form[action$="plan/join"] input[name=age_ok]').count():
        tp.check('form[action$="plan/join"] input[name=age_ok]')
    t.press('form[action$="plan/join"] button')
    uid = db('SELECT id FROM users WHERE email = ?', (email,))
    check(f'[{t.label}] B9 account created', len(uid) == 1, uid)
    uid = uid[0][0] if uid else 0
    token = php('echo rmt_token_issue(%d, "verify");' % uid)
    t.go('/verify-email?token=' + token)
    t.press('form[action$="verify-email/confirm"] button')
    check(f'[{t.label}] B9 the trip is live', bool(db('SELECT 1 FROM trips WHERE user_id = ?', (uid,))))
    t.answer_age('1988-07-09')
    bp = db("SELECT id FROM buddy_posts WHERE user_id = ? AND status = 'open'", (uid,))
    check(f'[{t.label}] B9 the buddy post went live after the age step', len(bp) == 1, bp)
    check(f'[{t.label}] B9 lands on the buddy post', bool(bp) and f'/buddy/{bp[0][0]}' in tp.url, tp.url)

    for m in (a, o, y, t):
        check(f'[{m.label}] no script errors', not m.errs, m.errs[:3])
    b.close()


with sync_playwright() as p:
    journey(p, ('desktop', DESK), ('mobile', PHONE))
    journey(p, ('mobile', PHONE), ('desktop', DESK))

print(f'\nbuddy_journey_e2e: {len(fails)} failed' if fails else '\nbuddy_journey_e2e: all passed')
sys.exit(1 if fails else 0)
