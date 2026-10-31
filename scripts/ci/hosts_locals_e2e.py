"""
Hosts & Locals end to end, in a real browser, against a LOCAL server and its database.

    python scripts/ci/hosts_locals_e2e.py BASE

BASE is a local dev server (php -S) started with RMT_LOCAL_URL=BASE and no DATABASE_URL, so it
uses the same SQLite file this script seeds through app/bootstrap.php. Never point it at
production: it creates members and deletes them again at the end.

Journey: a visitor reads the hub and a city on a phone and a desktop; a member lists themselves
as a local; another applies to host and is refused an address; an admin approves the host; a
traveler asks the local to meet, the local accepts, messages open; a day later (simulated) the
traveler writes a reference, which shows on the local's profile; stays stay disabled throughout.
"""
import json, os, subprocess, sys
from playwright.sync_api import sync_playwright

BASE = sys.argv[1].rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PHP = os.environ.get('PHP', r'C:\Users\BL\tools\php83\php.exe')
PW = 'localpass123'
USERS = ['hle2e_local', 'hle2e_host', 'hle2e_trav', 'hle2e_admin']
fails = []


def php(code):
    env = dict(os.environ); env.pop('DATABASE_URL', None)
    r = subprocess.run([PHP, '-c', 'php.local.ini', '-r', 'require "app/bootstrap.php"; ' + code],
                       cwd=ROOT, capture_output=True, text=True, env=env)
    if r.returncode != 0:
        raise RuntimeError(r.stderr or r.stdout)
    return r.stdout


def check(name, cond, detail=''):
    print(('  PASS  ' if cond else '  FAIL  ') + name + ('' if cond else '  ' + str(detail)[:200]))
    if not cond:
        fails.append(name)


def cleanup():
    php("foreach (%s as $u) { $r = q_one('SELECT id FROM users WHERE username=?', [$u]); if (!$r) continue; $i = (int) $r['id'];"
        " foreach (['DELETE FROM member_references WHERE from_user_id=? OR to_user_id=?', 'DELETE FROM local_connects WHERE from_user_id=? OR to_user_id=?',"
        " 'DELETE FROM notifications WHERE user_id=? OR actor_id=?', 'DELETE FROM messages WHERE sender_id=? OR sender_id=?',"
        " 'DELETE FROM conversations WHERE user_lo_id=? OR user_hi_id=?'] as $s) q_run($s, [$i, $i]);"
        " q_run('DELETE FROM stay_requests WHERE guest_id=? OR host_id=?', [$i, $i]); q_run('DELETE FROM host_profiles WHERE user_id=?', [$i]); q_run('DELETE FROM moderation_log WHERE target_type=? AND target_id=?', ['host_profile', $i]);"
        " q_run('DELETE FROM profiles WHERE user_id=?', [$i]); q_run('DELETE FROM users WHERE id=?', [$i]); }" % json.dumps(USERS).replace('"', "'"))


cleanup()
dest = json.loads(php("echo json_encode(q_one(\"SELECT id, slug, name FROM destinations WHERE slug='lisbon-portugal'\") ?: q_one('SELECT id, slug, name FROM destinations ORDER BY id LIMIT 1'));"))
php("$now = date('Y-m-d H:i:s'); $h = password_hash('%s', PASSWORD_DEFAULT);"
    " foreach (%s as $u) { q_run('INSERT INTO users (username,email,password_hash,role,status,email_verified_at,birthdate,created_at) VALUES (?,?,?,?,?,?,?,?)',"
    " [$u, $u.'@example.invalid', $h, $u === 'hle2e_admin' ? 'admin' : 'member', 'active', $now, '1990-05-05', $now]);"
    " rmt_profile_ensure((int) q_one('SELECT id FROM users WHERE username=?', [$u])['id']); }"
    " q_run(\"UPDATE profiles SET languages='en,pt', bio='I live here and love showing people the food markets.' WHERE user_id=(SELECT id FROM users WHERE username='hle2e_local')\");"
    " q_run(\"UPDATE profiles SET display_name='Trav Eler', bio='Slow traveler who likes markets and long walks.' WHERE user_id=(SELECT id FROM users WHERE username='hle2e_trav')\");"
    % (PW, json.dumps(USERS).replace('"', "'")))
uid = lambda u: int(php("echo q_one('SELECT id FROM users WHERE username=?', ['%s'])['id'];" % u))

try:
    with sync_playwright() as p:
        br = p.chromium.launch()

        def page_for(mobile=False):
            ctx = br.new_context(viewport={'width': 390, 'height': 844} if mobile else {'width': 1366, 'height': 900},
                                 is_mobile=mobile, has_touch=mobile)
            ctx.add_cookies([{'name': 'rmt_design', 'value': 'v2', 'url': BASE}])  # production runs the v2 design
            pg = ctx.new_page(); errs = []
            pg.on('pageerror', lambda e: errs.append(str(e)))
            pg.on('console', lambda m: errs.append(m.text) if m.type == 'error' else None)
            return pg, errs

        def login(pg, u):
            pg.goto(BASE + '/login')
            pg.fill('form[action$="login"] input[name=email]', u + '@example.invalid')
            pg.fill('form[action$="login"] input[name=password]', PW)
            pg.locator('form[action$="login"] button').first.click(no_wait_after=True)
            pg.wait_for_url(lambda url: '/login' not in url, timeout=15000)

        def no_hscroll(pg, label):
            w = pg.evaluate('document.documentElement.scrollWidth'); vw = pg.evaluate('window.innerWidth')
            check('no horizontal scroll ' + label, w <= vw, '%s > %s' % (w, vw))

        # Visitor, phone and desktop.
        for mobile in (True, False):
            tag = '[phone]' if mobile else '[desktop]'
            pg, errs = page_for(mobile)
            pg.goto(BASE + '/hosts-locals')
            check(tag + ' hub title', 'Experience the world through the people who live there' in pg.inner_text('h1'))
            check(tag + ' three experiences', all(pg.locator('.hl-exp h3', has_text=t).count() == 1 for t in ['Meet a Local', 'Find a Travel Buddy', 'Stay With a Local']))
            no_hscroll(pg, tag + ' hub')
            pg.fill('#hl-where', dest['name']); pg.locator('.hl-search button').click()
            pg.wait_for_url('**/hosts-locals/destinations/' + dest['slug'] + '**')
            check(tag + ' search lands on the city', ('Meet people in ' + dest['name']) in pg.inner_text('h1'))
            no_hscroll(pg, tag + ' city')
            for path in ['/hosts-locals/how-it-works', '/hosts-locals/safety', '/hosts-locals/become-a-host', '/hosts-locals/destinations']:
                r = pg.goto(BASE + path); check(tag + ' ' + path + ' 200', r.status == 200, r.status); no_hscroll(pg, tag + ' ' + path)
            if mobile:
                pg.goto(BASE + '/hosts-locals')
                pg.locator('.v2-menu-btn').click(); pg.wait_for_timeout(300)
                check('[phone] menu has Hosts & Locals', pg.locator('.v2-sheet a[href$="/hosts-locals"]').is_visible())
            check(tag + ' no script errors', not errs, errs)
            pg.context.close()

        # A local lists themselves.
        lp, lerr = page_for(True); login(lp, 'hle2e_local')
        lp.goto(BASE + '/hosts-locals/me')
        lp.select_option('#l-dest', str(dest['id']))
        lp.check('input[name="offers[]"][value="coffee"]'); lp.check('input[name="offers[]"][value="food"]')
        lp.fill('#l-avail', 'Weekday evenings')
        lp.locator('#local button[type=submit]').click(); lp.wait_for_load_state()
        check('local listing lands on their city', '/hosts-locals/destinations/' + dest['slug'] in lp.url, lp.url)
        check('the local does not see themselves', lp.locator('.hl-person', has_text='Weekday evenings').count() == 0)  # nobody sees themselves
        lp.goto(BASE + '/hosts-locals/me')
        check('the roles page has an invite link', 'hosts-locals?ref=hle2e_local' in lp.input_value('#hl-invite-link'))
        lp.goto(BASE + '/u/hle2e_local')
        check('own profile links back to the local settings', lp.locator('a.chip[href*="hosts-locals"]').count() >= 1)
        lp.goto(BASE + '/hosts-locals/destinations/' + dest['slug'])
        # The welcome screen sends a would-be host to the host form.
        wp, werr = page_for(True); login(wp, 'hle2e_trav')
        wp.goto(BASE + '/welcome'); wp.check('input[name=role_host]')
        wp.locator('form.onboard button[type=submit]').last.click(); wp.wait_for_load_state()
        check('welcome "host" goes to the host form', '/hosts-locals/become-a-host' in wp.url, wp.url)
        wp.goto(BASE + '/welcome'); wp.check('input[name=role_local]')
        wp.locator('form.onboard button[type=submit]').last.click(); wp.wait_for_load_state()
        check('welcome "local" goes to the local settings', '/hosts-locals/me' in wp.url, wp.url)
        check('no script errors on welcome', not werr, werr)
        wp.context.close()
        # A host applies; an address is refused.
        hp, herr = page_for(False); login(hp, 'hle2e_host')
        hp.goto(BASE + '/hosts-locals/become-a-host')
        hp.select_option('#h-dest', str(dest['id'])); hp.fill('#h-hood', '12 Rua Augusta')
        hp.select_option('#h-space', 'private_room')
        hp.fill('#h-about', 'A bright private room with a desk and a fan, ten minutes from the river on foot.')
        hp.select_option('#h-pets', 'none'); hp.select_option('#h-smoke', 'no'); hp.check('input[name=agree]')
        hp.locator('.hl-form button[type=submit]').click(); hp.wait_for_load_state()
        check('an address in the neighbourhood is refused', 'never a street' in hp.inner_text('.errors'))
        hp.fill('#h-hood', 'Alfama'); hp.check('input[name=agree]')
        hp.locator('.hl-form button[type=submit]').click(); hp.wait_for_load_state()
        check('the application is pending', '/hosts-locals/me' in hp.url and 'Status: Pending' in hp.inner_text('#host'), hp.url)
        tp, terr = page_for(True)
        tp.goto(BASE + '/hosts-locals/destinations/' + dest['slug'])
        check('a pending host is not public', tp.locator('.hl-person', has_text='Alfama').count() == 0)
        # Admin approves.
        ap, aerr = page_for(False); login(ap, 'hle2e_admin')
        ap.goto(BASE + '/admin/hosts')
        row = ap.locator('tr', has_text='hle2e_host')
        check('admin sees the application', row.count() == 1)
        row.locator('button[value=approved]').click(); ap.wait_for_load_state()
        tp.goto(BASE + '/hosts-locals/destinations/' + dest['slug'])
        host_card = tp.locator('.hl-person', has_text='Alfama')
        check('the approved host is public with a neighbourhood only', host_card.count() == 1 and 'Rua' not in host_card.inner_text())
        check('a visitor is asked to join to request a stay', host_card.locator('a', has_text='Join to ask to stay').count() == 1)
        check('no identity badge is claimed', 'Identity checked' not in host_card.inner_text())
        # Traveler asks the local; local accepts; messages open.
        login(tp, 'hle2e_trav')
        tp.goto(BASE + '/hosts-locals/destinations/' + dest['slug'] + '?role=locals')
        card = tp.locator('.hl-person', has_text='food')
        check('the traveler finds the local', card.count() >= 1)
        tp.locator('.hl-person', has=tp.locator('a[href$="/u/hle2e_local"]')).locator('button', has_text='Ask to meet').click(); tp.wait_for_load_state()
        check('the request is waiting', tp.locator('.hl-person', has=tp.locator('a[href$="/u/hle2e_local"]')).locator('text=Asked, waiting').count() == 1)
        lp.goto(BASE + '/hosts-locals/me')
        check('the local sees the request', lp.locator('a[href$="/u/hle2e_trav"]').count() >= 1)
        lp.locator('button[value=accepted]').first.click(); lp.wait_for_load_state()
        tp.goto(BASE + '/messages/hle2e_local')
        check('messages open after a yes', tp.locator('form.msg-composer textarea').count() == 1)
        tp.goto(BASE + '/u/hle2e_local/references')
        check('a reference is not possible on day one', 'opens a day after you connect' in tp.inner_text('main'))
        php("q_run(\"UPDATE local_connects SET decided_at=? WHERE to_user_id=(SELECT id FROM users WHERE username='hle2e_local')\", [date('Y-m-d H:i:s', time()-2*86400)]);")
        tp.goto(BASE + '/u/hle2e_local/references')
        tp.select_option('#r-rating_overall', '5'); tp.select_option('#r-rating_reliability', '5')
        tp.fill('#r-body', 'Met for coffee, then a walk through the market. Generous with time and tips.')
        tp.locator('form.hl-form button[type=submit]').click(); tp.wait_for_load_state()
        check('the reference is published', 'Generous with time' in tp.inner_text('main'))
        tp.goto(BASE + '/u/hle2e_local')
        check('the profile links one reference', tp.locator('a.chip', has_text='1 reference').count() == 1)
        no_hscroll(tp, '[phone] profile')
        tp.goto(BASE + '/u/hle2e_trav/references')
        check('nobody can reference themselves', tp.locator('form.hl-form').count() == 0)
        # A stay: the traveler asks the approved host, the host accepts, the traveler confirms.
        tp.goto(BASE + '/hosts-locals/destinations/' + dest['slug'] + '?role=hosts')
        tp.locator('.hl-person', has_text='Alfama').locator('a', has_text='Ask to stay').click(); tp.wait_for_load_state()
        check('the stay form opens', '/stay/new/hle2e_host' in tp.url, tp.url)
        no_hscroll(tp, '[phone] stay form')
        import datetime as _dt
        d1 = (_dt.date.today() + _dt.timedelta(days=12)).isoformat(); d2 = (_dt.date.today() + _dt.timedelta(days=14)).isoformat()
        tp.fill('#s-from', d1); tp.fill('#s-to', d2); tp.fill('#s-guests', '1')
        tp.fill('#s-intro', 'Visiting for a long weekend to walk the old town and try the bakeries. Quiet and tidy guest.')
        tp.check('input[name=agree]'); tp.locator('form.hl-form button[type=submit]').click(); tp.wait_for_load_state()
        check('the request is sent', '/stay/' in tp.url and 'Waiting for the host' in tp.inner_text('main'), tp.url)
        stay_url = tp.url
        hp.goto(BASE + '/stays')
        check('the host sees the request', hp.locator('a[href*="/stay/"]', has_text='Trav Eler').count() == 1)
        hp.goto(stay_url); hp.locator('form[action$="/accept"] button').click(); hp.wait_for_load_state()
        check('the host accepts', 'Accepted' in hp.inner_text('main'))
        tp.goto(stay_url); tp.locator('form[action$="/confirm"] button').click(); tp.wait_for_load_state()
        check('the traveler confirms', 'Confirmed' in tp.inner_text('main'))
        check('messages are linked after the yes', tp.locator('a[href$="/messages/hle2e_host"]').count() == 1)
        check('the stay can be reported', tp.locator('a[href*="report?target_type=stay"]').count() == 1)
        tp.goto(BASE + '/messages/hle2e_host')
        check('the guest can message the host', tp.locator('form.msg-composer textarea').count() == 1)
        sp, _ = page_for(False); login(sp, 'hle2e_admin')
        r = sp.goto(stay_url); check('nobody else can open the stay', r.status == 404, r.status); sp.context.close()
        for label, errs in [('local', lerr), ('host', herr), ('traveler', terr), ('admin', aerr)]:
            check('no script errors for the ' + label, not errs, errs)
        br.close()
finally:
    cleanup()

print('\nhosts_locals_e2e: %s' % ('%d failed' % len(fails) if fails else '0 failed'))
sys.exit(1 if fails else 0)
