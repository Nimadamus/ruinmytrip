"""A member uploads photos through the real forms in a real browser (Playwright, headless), desktop and phone.

  P1  join, confirm the email (uploads need a confirmed member, as in production)
  P2  a review published with a photo: kept, shown on the review page, image loads
  P3  a trip posted with a photo, then one more added from the trip page's own Add photos control
  P4  a profile photo: saved and shown on the public profile
  P5  every stored photo is served as an image with nosniff, and the GPS the camera wrote is gone
  P6  refused, with a plain visible message and never a 500: a .php renamed .jpg, a file over 8MB,
      on each of the three forms
  P7  two 7MB phone photos chosen together are both added (the post limit used to be 12M, which
      dropped the whole post as "your form session expired"); a post over the limit says the
      photos were too large
  Every page: no horizontal scroll, no script errors.

usage: python scripts/ci/photo_upload_e2e.py BASE DB_PATH
E2E_DESIGN=v2 runs it in the redesign. Writes only to the dev database it is pointed at.
Run the server with the production upload limits (Dockerfile: upload_max_filesize 10M,
post_max_size 64M), or PHP cuts the large cases off before the app sees them.
"""
import io, os, sys, sqlite3, random, string, subprocess, tempfile, datetime
from PIL import Image
from playwright.sync_api import sync_playwright

BASE, DB = sys.argv[1].rstrip('/'), sys.argv[2]
DESIGN = os.environ.get('E2E_DESIGN', '')
PHP = os.environ.get('PHP', r'C:\Users\BL\tools\php83\php.exe')
PLACE = ('oceanario-de-lisboa-lisbon', 151)
LISBON = 78
UA = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) '
      'Chrome/129.0.0.0 Safari/537.36')
UA_PHONE = ('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 '
            '(KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1')
GPS_IFD = 0x8825
MB = 1024 * 1024
fails = []
TMP = tempfile.mkdtemp(prefix='rmt_photo_e2e_')


def check(name, cond, detail=''):
    print(('  PASS  ' if cond else '  FAIL  ') + name + (('  -- ' + str(detail)[:300]) if (detail and not cond) else ''))
    if not cond:
        fails.append(name)
    return cond


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


def jpeg(name, w=1200, h=800, pad=0):
    """A real JPEG carrying camera EXIF with GPS (Lisbon), optionally padded after its end marker
    to a given size: still a decodable JPEG, just a heavy one, as phone photos are."""
    img = Image.linear_gradient('L').resize((w, h)).convert('RGB')
    exif = Image.Exif()
    exif[0x010F] = 'E2ECam'
    exif[0x0110] = 'E2E Model'
    gps = exif.get_ifd(GPS_IFD)
    gps.update({1: 'N', 2: (38.0, 42.0, 0.0), 3: 'W', 4: (9.0, 8.0, 0.0)})
    buf = io.BytesIO()
    img.save(buf, 'JPEG', quality=90, exif=exif)
    data = buf.getvalue()
    if pad:
        data += b'\0' * max(0, pad - len(data))
    path = os.path.join(TMP, name)
    with open(path, 'wb') as f:
        f.write(data)
    return path


def has_gps(data):
    im = Image.open(io.BytesIO(data))
    ex = im.getexif()
    return GPS_IFD in ex or bool(ex.get_ifd(GPS_IFD))


GOOD = jpeg('good_trip_photo.jpg')
assert has_gps(open(GOOD, 'rb').read()), 'the test photo must carry GPS for the strip check to mean anything'
GOOD2 = jpeg('second_photo.jpg', 900, 1200)
BIG = jpeg('nine_mb_photo.jpg', pad=9 * MB)           # over the 8MB per photo rule, under PHP's 10M
HEAVY_A = jpeg('heavy_a.jpg', pad=7 * MB)             # each one fine on its own ...
HEAVY_B = jpeg('heavy_b.jpg', pad=7 * MB)             # ... and together (14MB) too
FAKE = os.path.join(TMP, 'shell.jpg')
with open(FAKE, 'wb') as f:
    f.write(b'<?php echo "uploaded code ran"; system($_GET["c"] ?? "id"); ?>\n')


def run(p, dev, ctxargs):
    tag = lambda s: f'[{dev}] {s}'
    db('DELETE FROM rate_limits')
    b = p.chromium.launch(headless=True)
    ctx = b.new_context(**ctxargs)
    if DESIGN:
        ctx.add_cookies([{'name': 'rmt_design', 'value': DESIGN, 'url': BASE}])
    page = ctx.new_page()
    errs, statuses = [], []
    page.on('pageerror', lambda e: errs.append(str(e)))
    page.on('response', lambda r: statuses.append((r.status, r.url)) if r.request.resource_type == 'document' else None)

    def settle():
        try:
            page.wait_for_load_state('networkidle', timeout=8000)
        except Exception:
            page.wait_for_load_state('load')

    def go(path):
        page.goto(BASE + path); settle()
        check(tag('no horizontal scroll ' + path.split('?')[0]),
              page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))

    def press(sel, timeout=30000):
        statuses.clear()
        page.locator(sel).first.click(timeout=timeout)
        settle()
        return max([s for s, _ in statuses] or [0])

    def text():
        return page.inner_text('body')

    def served(key, where):
        """The stored photo is on this page, loads, and is served safely with no GPS."""
        stem = key.split('.')[0]
        img = page.locator(f'img[src*="{stem}"]:visible')   # the phone menu keeps a hidden copy of the avatar
        if not check(tag(f'P5 {where}: the photo is on the page'), img.count() > 0, page.url):
            return
        img.first.scroll_into_view_if_needed()
        page.wait_for_function('(el) => el.complete', arg=img.first.element_handle(), timeout=10000)
        nw = img.first.evaluate('el => el.naturalWidth')
        check(tag(f'P5 {where}: the image loads in the browser'), nw > 0, nw)
        src = img.first.get_attribute('src')
        r = page.request.get(src if src.startswith('http') else BASE + src)
        ct = r.headers.get('content-type', '')
        check(tag(f'P5 {where}: served as an image'), r.status == 200 and ct.startswith('image/'), (r.status, ct))
        check(tag(f'P5 {where}: served with nosniff'), r.headers.get('x-content-type-options', '') == 'nosniff')
        body = r.body()
        check(tag(f'P5 {where}: the camera GPS is gone'), not has_gps(body))
        check(tag(f'P5 {where}: the camera make is gone'), b'E2ECam' not in body)

    def refused(label, want, status):
        t = text()
        check(tag(f'P6 {label} is not a server error'), 0 < status < 500, status)
        check(tag(f'P6 {label} is refused with a visible message'), want in t, t[:300].replace('\n', ' '))

    stamp = ''.join(random.choices(string.ascii_lowercase, k=6))
    email = f'e2e_photo_{dev}_{stamp}@example.com'
    pw = 'longenough123'

    # P1 join and confirm
    page.goto(BASE + '/register'); settle()
    page.fill('form[action$="register"] input[name=email]', email)
    page.fill('form[action$="register"] input[name=password]', pw)
    page.check('form[action$="register"] input[name=age_ok]')
    press('form[action$="register"] button')
    row = db('SELECT id, username FROM users WHERE email = ?', (email,))
    check(tag('P1 the account is created'), len(row) == 1, page.url)
    uid, uname = row[0]
    token = php('echo rmt_token_issue(%d, "verify");' % uid)
    page.goto(BASE + '/verify-email?token=' + token); settle()
    press('form[action$="verify-email/confirm"] button')
    check(tag('P1 the email is confirmed'), db('SELECT email_verified_at FROM users WHERE id = ?', (uid,))[0][0] is not None)

    # P2 a review with a photo
    go(f'/review/new?place={PLACE[1]}&src=place')
    page.click('label[for=rate-4]')
    page.fill('textarea[name=body]', f'Huge central tank and the sunfish ({stamp}) are the highlight, '
                                     'but weekend mornings are packed with school groups.')
    page.set_input_files('input#photos', GOOD)
    st = press('button[name=action][value=publish]')
    r = db('SELECT id FROM reviews WHERE user_id = ? AND place_id = ?', (uid, PLACE[1]))
    check(tag('P2 the review is published'), st < 500 and len(r) == 1 and '/review/' in page.url, (st, page.url))
    rid = r[0][0] if r else 0
    ph = db('SELECT storage_key FROM review_photos WHERE review_id = ?', (rid,))
    check(tag('P2 the photo is kept with it'), len(ph) == 1, ph)
    check(tag('P2 no photo error is shown'), 'not added' not in text(), text()[:300])
    if ph:
        served(ph[0][0], 'review page')
        c3 = b.new_context(**ctxargs); p3 = c3.new_page()
        p3.goto(f'{BASE}/review/{rid}'); p3.wait_for_load_state('networkidle')
        check(tag('P2 a stranger sees the photo too'), p3.locator(f'img[src*="{ph[0][0].split(".")[0]}"]').count() > 0)
        c3.close()

    # P6 on the review form: wrong type and too large, added from the edit page
    for label, f, want in [('review: a .php renamed .jpg', FAKE, 'Photos must be JPEG, PNG or WebP'),
                           ('review: a 9MB photo', BIG, 'too large (8MB max)')]:
        go(f'/review/{rid}/edit')
        page.set_input_files('input#photos', f)
        st = press('button[name=action][value=publish]')
        refused(label, want, st)
    check(tag('P6 review: nothing refused was stored'),
          db('SELECT COUNT(*) FROM review_photos WHERE review_id = ?', (rid,))[0][0] == 1)

    # P3 a trip with a photo, then one more from the trip page
    go('/trip/new')
    page.select_option('select#destination_id', str(LISBON))
    start = datetime.date.today() + datetime.timedelta(days=40 + random.randint(0, 300))
    page.fill('input#date_from', start.isoformat())
    page.fill('input#date_to', (start + datetime.timedelta(days=4)).isoformat())
    if page.locator('input#title').count():
        page.fill('input#title', f'Lisbon photo check {stamp}')
    page.set_input_files('input#photos', GOOD)
    st = press('form[action$="trip/new"] button[type=submit], form[action$="trip/new"] button:not([type])')
    t = db('SELECT id, slug FROM trips WHERE user_id = ? ORDER BY id DESC', (uid,))
    check(tag('P3 the trip is posted'), st < 500 and len(t) == 1, (st, page.url, text()[:200]))
    tid, tslug = t[0] if t else (0, '')
    tp = db('SELECT storage_key FROM trip_photos WHERE trip_id = ? ORDER BY id', (tid,))
    check(tag('P3 the photo is kept with it'), len(tp) == 1, tp)
    go(f'/trip/{tid}/{tslug}')
    if tp:
        served(tp[0][0], 'trip page')
    add = f'form[action$="trip/{tid}/photos"] input[type=file]'
    check(tag('P3 the trip page offers Add photos'), page.locator(add).count() == 1)
    statuses.clear()
    with page.expect_navigation():
        page.set_input_files(add, GOOD2)   # the control submits itself on change
    settle()
    tp = db('SELECT storage_key FROM trip_photos WHERE trip_id = ? ORDER BY id', (tid,))
    check(tag('P3 Add photos adds it'), len(tp) == 2 and 'Added' in text(), (tp, text()[:200]))
    if len(tp) == 2:
        served(tp[1][0], 'trip page, added later')
    for label, f, want in [('trip: a .php renamed .jpg', FAKE, 'Photos must be JPEG, PNG or WebP'),
                           ('trip: a 9MB photo', BIG, 'too large (8MB max)')]:
        go(f'/trip/{tid}/{tslug}')
        statuses.clear()
        with page.expect_navigation():
            page.set_input_files(add, f)
        settle()
        refused(label, want, max([s for s, _ in statuses] or [0]))
    check(tag('P6 trip: nothing refused was stored'),
          db('SELECT COUNT(*) FROM trip_photos WHERE trip_id = ?', (tid,))[0][0] == 2)

    # P4 profile photo
    for label, f, want in [('profile: a .php renamed .jpg', FAKE, 'Photos must be JPEG, PNG or WebP'),
                           ('profile: a 9MB photo', BIG, 'too large (8MB max)')]:
        go(f'/u/{uname}/edit')
        page.set_input_files('input#avatar', f)
        st = press('form[action$="/edit"] button[type=submit], form[action$="/edit"] button:not([type])')
        refused(label, want, st)
    check(tag('P6 profile: nothing refused was stored'),
          not (db('SELECT avatar_key FROM profiles WHERE user_id = ?', (uid,)) or [[None]])[0][0])
    go(f'/u/{uname}/edit')
    page.set_input_files('input#avatar', GOOD2)
    st = press('form[action$="/edit"] button[type=submit], form[action$="/edit"] button:not([type])')
    av = (db('SELECT avatar_key FROM profiles WHERE user_id = ?', (uid,)) or [[None]])[0][0]
    check(tag('P4 the profile photo is saved'), st < 500 and bool(av), (st, av, text()[:200]))
    if av:
        go(f'/u/{uname}?x={stamp}')
        served(av, 'public profile')

    # P7 two heavy phone photos chosen together: at the old 12M post limit PHP dropped the whole post
    go(f'/review/{rid}/edit')
    page.set_input_files('input#photos', [HEAVY_A, HEAVY_B])
    st = press('button[name=action][value=publish]')
    t = text()
    check(tag('P7 two 7MB photos in one post are not a server error'), 0 < st < 500, st)
    check(tag('P7 they are not called an expired form session'), 'session expired' not in t, t[:200])
    check(tag('P7 both are added'), db('SELECT COUNT(*) FROM review_photos WHERE review_id = ?', (rid,))[0][0] == 3,
          t[:300].replace('\n', ' '))
    # P7b a post over the server's whole limit says the photos were too large, not "session expired"
    go(f'/trip/{tid}/edit')
    page.set_input_files('input#photos', [BIG] * 8)   # 72MB, over the 64M post limit
    st = press(f'form[action$="trip/{tid}/edit"] button[type=submit]', timeout=180000)
    t = text()
    check(tag('P7 a post over the limit is not a server error'), 0 < st < 500, st)
    check(tag('P7 it is not called an expired form session'), 'session expired' not in t, t[:200])
    check(tag('P7 it says the photos were too large'), 'too large' in t.lower(), t[:300].replace('\n', ' '))

    # P8 the trip goes private: its photos follow it. The owner still sees them on the page; a
    # signed-out visitor holding the exact photo URL gets a 404, and nothing says a shared cache
    # may keep a copy.
    db("UPDATE trips SET visibility = 'private' WHERE id = ?", (tid,))
    key = db('SELECT storage_key FROM trip_photos WHERE trip_id = ? ORDER BY id', (tid,))[0][0]
    go(f'/trip/{tid}/{tslug}?x={stamp}')
    nw = page.evaluate("[...document.images].filter(i => i.src.includes(%r)).map(i => i.naturalWidth)[0] || 0" % key[:32])
    check(tag('P8 private trip: the owner still sees its photo'), nw > 0, nw)
    r = page.request.get(f'{BASE}/media/{key}')
    check(tag('P8 private trip: the owner fetches it, never shared-cached'),
          r.status == 200 and 'private' in r.headers.get('cache-control', ''), (r.status, r.headers.get('cache-control')))
    anon = b.new_context(**ctxargs)
    for path in (f'/media/{key}', f'/media/w/480/{key}', f'/media/w/960/{key}'):
        st = anon.request.get(BASE + path).status
        check(tag(f'P8 private trip: signed out gets 404 for {path.split(key)[0]}'), st == 404, st)
    anon.close()
    db("UPDATE trips SET visibility = 'public' WHERE id = ?", (tid,))
    r = b.new_context(**ctxargs).request.get(f'{BASE}/media/{key}')
    check(tag('P8 public again: anyone gets it, cached an hour at most'),
          r.status == 200 and r.headers.get('cache-control', '') == 'public, max-age=3600', (r.status, r.headers.get('cache-control')))

    check(tag('no script errors on any page'), not errs, errs[:3])
    b.close()


with sync_playwright() as p:
    run(p, 'desktop', dict(viewport={'width': 1366, 'height': 860}, user_agent=UA))
    run(p, 'mobile', dict(viewport={'width': 390, 'height': 844}, is_mobile=True, has_touch=True,
                          device_scale_factor=3, user_agent=UA_PHONE))
print(f'\nphoto_upload_e2e{" (" + DESIGN + ")" if DESIGN else ""}: {len(fails)} failed')
sys.exit(1 if fails else 0)
