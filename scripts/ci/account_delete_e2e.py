"""A member deletes their own account through the real pages (Playwright, headless), phone and desktop.

  D1  settings show a Delete account section that links to the confirmation page
  D2  the confirmation page explains what is deleted and what is kept; a wrong password or a
      mistyped username deletes nothing
  D3  username + password deletes the account: confirmation page, signed out, profile gone,
      the old email and password no longer sign in
  D4  the other member still has the conversation, read only, from "Deleted member"
  D5  an admin is told why the account cannot be deleted here, and gets no form
  Every page: no horizontal scroll.

usage: python scripts/ci/account_delete_e2e.py BASE DB_PATH [SHOT_DIR]
Writes only to the dev database it is pointed at.
"""
import e2e_db
import os, sys, sqlite3, random, string, subprocess, datetime as dt
from playwright.sync_api import sync_playwright

BASE, DB = sys.argv[1].rstrip('/'), sys.argv[2]
SHOTS = sys.argv[3] if len(sys.argv) > 3 else ''
PHP = os.environ.get('PHP', r'C:\Users\BL\tools\php83\php.exe')
PW = 'longenough123'
fails = []


def check(name, cond, detail=''):
    print(('  PASS  ' if cond else '  FAIL  ') + name + (('  -- ' + str(detail)[:300]) if (detail and not cond) else ''))
    if not cond:
        fails.append(name)
    return cond


def db(sql, args=()):
    c = e2e_db.connect(DB)
    try:
        rows = c.execute(sql, args).fetchall()
        c.commit()
        return rows
    finally:
        c.close()


HASH = subprocess.run([PHP, '-r', f'echo password_hash("{PW}", PASSWORD_BCRYPT);'], capture_output=True, text=True).stdout.strip()
NOW = dt.datetime.now().strftime('%Y-%m-%d %H:%M:%S')


def mail_for(name):
    # E2E_MAIL_TO (e.g. me+del{role}@gmail.com) sends the run's mail to a real inbox on a staging run.
    return os.environ.get('E2E_MAIL_TO', '{role}@example.com').format(role=name, stamp='')


def seed_user(name, role='user'):
    db('INSERT INTO users (username, email, password_hash, role, status, created_at, email_verified_at, age_confirmed_at) '
       'VALUES (?,?,?,?,?,?,?,?)', (name, mail_for(name), HASH, role, 'active', NOW, NOW, NOW))
    uid = db('SELECT id FROM users WHERE username = ?', (name,))[0][0]
    db('INSERT INTO profiles (user_id, display_name, bio, credibility_score) VALUES (?,?,?,0)', (uid, name.title(), f'bio of {name}'))
    return uid


class Member:
    def __init__(self, browser, dev, label):
        w, h, mobile = dev
        self.ctx = browser.new_context(viewport={'width': w, 'height': h}, is_mobile=mobile, has_touch=mobile)
        self.page = self.ctx.new_page()
        self.label = label

    def settle(self):
        try:
            self.page.wait_for_load_state('networkidle', timeout=8000)
        except Exception:
            self.page.wait_for_load_state('load')

    def scroll_ok(self, what):
        check(f'[{self.label}] no horizontal scroll {what}',
              self.page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))

    def go(self, path):
        resp = self.page.goto(BASE + path); self.settle()
        self.scroll_ok(path.split('?')[0])
        return resp

    def login(self, email, pw=PW):
        self.go('/login')
        self.page.fill('form[action$="login"] input[name=email]', email)
        self.page.fill('form[action$="login"] input[name=password]', pw)
        self.page.locator('form[action$="login"] button').first.click(); self.settle()

    def text(self):
        return self.page.inner_text('body')

    def close(self):
        self.ctx.close()


def run(p, dev_name, dev):
    stamp = ''.join(random.choices(string.ascii_lowercase, k=6))
    db('DELETE FROM rate_limits')
    dana = f'dana_{stamp}'
    omar = f'omar_{stamp}'
    boss = f'boss_{stamp}'
    did, oid, bid = seed_user(dana), seed_user(omar), seed_user(boss, 'admin')
    lo, hi = min(did, oid), max(did, oid)
    db('INSERT INTO conversations (user_lo_id, user_hi_id, last_message_at, created_at) VALUES (?,?,?,?)', (lo, hi, NOW, NOW))
    conv = db('SELECT id FROM conversations WHERE user_lo_id=? AND user_hi_id=?', (lo, hi))[0][0]
    db('INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?,?,?,?)', (conv, did, 'see you in Lisbon', NOW))
    db('INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?,?,?,?)', (conv, oid, 'great, see you there', NOW))
    dest = db('SELECT id FROM destinations ORDER BY id LIMIT 1')[0][0]
    db("INSERT INTO reviews (user_id, destination_id, subject_type, subject_name, rating, title, body, status, created_at, slug) "
       "VALUES (?,?,?,?,?,?,?,?,?,?)", (did, dest, 'destination', 'Somewhere', 3, 'Dana e2e review', 'dana e2e words', 'published', NOW, f'dana-e2e-{stamp}'))

    b = p.chromium.launch(headless=True)
    try:
        a = Member(b, dev, f'{dev_name} member')
        a.login(mail_for(dana))
        check(f'[{a.label}] signed in', '/login' not in a.page.url, a.page.url)

        # D1 settings section
        a.go(f'/u/{dana}/edit')
        sec = a.page.locator('#delete-account')
        check(f'[{a.label}] D1 settings show Delete account', sec.count() == 1 and 'Delete account' in sec.inner_text())
        if SHOTS:
            sec.scroll_into_view_if_needed()
            sec.screenshot(path=os.path.join(SHOTS, f'settings_delete_section_{dev[0]}.png'))
        sec.locator('a[href$="account/delete"]').click(); a.settle()
        check(f'[{a.label}] D1 link opens the confirmation page', a.page.url.endswith('/account/delete'), a.page.url)
        a.scroll_ok('/account/delete')

        # D2 explanation and refusals
        t = a.text()
        check(f'[{a.label}] D2 says what is deleted and what is kept',
              'Deleted for good' in t and 'Kept, without your name or profile' in t and 'Deleted member' in t)
        check(f'[{a.label}] D2 offers the emailed link alternative', a.page.locator('form[action$="account/delete/email"]').count() == 1)
        if SHOTS:
            a.page.screenshot(path=os.path.join(SHOTS, f'account_delete_page_{dev[0]}.png'), full_page=True)
        a.page.fill('input[name=confirm_username]', dana)
        a.page.fill('input[name=password]', 'wrong password')
        a.page.locator('#account-delete-submit').click(); a.settle()
        check(f'[{a.label}] D2 wrong password refused', 'That password is not right' in a.text()
              and db('SELECT status FROM users WHERE id=?', (did,))[0][0] == 'active')
        a.page.fill('input[name=confirm_username]', 'someone_else')
        a.page.fill('input[name=password]', PW)
        a.page.locator('#account-delete-submit').click(); a.settle()
        check(f'[{a.label}] D2 wrong username refused', 'Type your username' in a.text()
              and db('SELECT status FROM users WHERE id=?', (did,))[0][0] == 'active')

        # D3 the deletion
        a.page.fill('input[name=confirm_username]', dana)
        a.page.fill('input[name=password]', PW)
        a.page.locator('#account-delete-submit').click(); a.settle()
        check(f'[{a.label}] D3 confirmation page', a.page.url.endswith('/account/deleted') and 'Your account is deleted' in a.text(), a.page.url)
        a.scroll_ok('/account/deleted')
        row = db('SELECT status, email FROM users WHERE id=?', (did,))[0]
        check(f'[{a.label}] D3 account deleted in the database', row[0] == 'deleted' and 'dana' not in row[1].split('@')[0][:5], row)
        check(f'[{a.label}] D3 review and profile gone', db('SELECT COUNT(*) FROM reviews WHERE user_id=?', (did,))[0][0] == 0
              and db('SELECT COUNT(*) FROM profiles WHERE user_id=?', (did,))[0][0] == 0)
        a.go('/settings')
        check(f'[{a.label}] D3 signed out', '/login' in a.page.url, a.page.url)
        r = a.page.goto(BASE + f'/u/{dana}'); a.settle()
        check(f'[{a.label}] D3 old profile is gone', r.status == 404, r.status)
        a.page.goto(BASE + '/account/deleted'); a.settle()
        check(f'[{a.label}] D3 confirmation is shown once', not a.page.url.endswith('/account/deleted'), a.page.url)
        a.login(mail_for(dana))
        check(f'[{a.label}] D3 old email and password do not sign in', '/login' in a.page.url, a.page.url)
        a.close()

        # D4 the other member keeps the conversation, read only
        o = Member(b, dev, f'{dev_name} other')
        o.login(mail_for(omar))
        o.go('/messages')
        check(f'[{o.label}] D4 inbox shows Deleted member', 'Deleted member' in o.text() and dana not in o.text())
        o.page.locator('a:has-text("Deleted member")').first.click(); o.settle()
        o.scroll_ok('/messages/thread')
        t = o.text()
        check(f'[{o.label}] D4 both messages readable', 'see you in Lisbon' in t and 'great, see you there' in t)
        check(f'[{o.label}] D4 read only, no reply box, no profile link',
              o.page.locator('form.msg-composer').count() == 0 and 'cannot reply' in t and o.page.locator(f'a[href*="/u/deleted_member"]').count() == 0)
        o.close()

        # D5 admin cannot self delete
        s = Member(b, dev, f'{dev_name} admin')
        s.login(mail_for(boss))
        s.go('/account/delete')
        check(f'[{s.label}] D5 staff told why, no form', 'Staff accounts cannot be deleted' in s.text()
              and s.page.locator('#account-delete-submit').count() == 0)
        s.go(f'/u/{boss}/edit')
        check(f'[{s.label}] D5 settings explain it too', 'Staff accounts cannot be deleted' in s.page.locator('#delete-account').inner_text())
        s.close()
    finally:
        b.close()


with sync_playwright() as p:
    for name, dev in [('phone', (390, 844, True)), ('desktop', (1366, 900, False))]:
        run(p, name, dev)

print(f'\n{len(fails)} FAILED' if fails else '\nALL PASS')
sys.exit(1 if fails else 0)
