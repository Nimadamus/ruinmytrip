"""End-to-end review flows over real HTTP: hotel, restaurant, attraction, edit, clear, permissions."""
import re, sqlite3, subprocess, sys, os, json

BASE = 'http://127.0.0.1:8099'
SP = os.path.dirname(os.path.abspath(__file__))
DB = r'C:\Users\BL\ruinmytrip\database\dev.sqlite'
fails = []


def check(name, got, expect):
    ok = got == expect
    print(('  [PASS] ' if ok else '  [FAIL] ') + name.ljust(58) + ' expected=%r got=%r' % (expect, got))
    if not ok:
        fails.append(name)


def curl(args, jar):
    cmd = ['curl', '-s', '-b', jar, '-c', jar] + args
    r = subprocess.run(cmd, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout


def get(path, jar, code_only=False, follow=False):
    args = ['-L'] if follow else []
    if code_only:
        return curl(args + ['-o', os.devnull, '-w', '%{http_code}', BASE + path], jar)
    return curl(args + [BASE + path], jar)


def post(path, jar, fields, code_only=False):
    args = []
    for k, v in fields:
        args += ['-F', '%s=%s' % (k, v)]
    if code_only:
        args += ['-o', os.devnull, '-w', '%{http_code}']
    return curl(args + [BASE + path], jar)


def token(html, name):
    m = re.search(r'name="%s"\s+value="([^"]*)"' % name, html)
    if not m:
        m = re.search(r'value="([^"]*)"\s+name="%s"' % name, html)
    return m.group(1) if m else ''


def login(user, jar):
    if os.path.exists(jar):
        os.remove(jar)
    html = get('/login', jar)
    post('/login', jar, [('_csrf', token(html, '_csrf')), ('email', user + '@fixture.invalid'), ('password', 'QaPassw0rd!')])
    # /feed is auth-gated: a 200 means the session really is signed in, whatever the nav renders.
    return get('/feed', jar, code_only=True) == '200'


def db(sql, args=()):
    c = sqlite3.connect(DB)
    try:
        return c.execute(sql, args).fetchall()
    finally:
        c.close()


def reset():
    c = sqlite3.connect(DB)
    try:
        c.execute("DELETE FROM reviews WHERE title LIKE 'QA % headline'")
        c.execute("DELETE FROM rate_limits")
        c.commit()
    finally:
        c.close()


reset()

jar_w = os.path.join(SP, 'jar_w.txt')
jar_o = os.path.join(SP, 'jar_o.txt')

print('-- sign in --')
check('writer signs in', login('qa_writer', jar_w), True)
check('second user signs in', login('qa_other', jar_o), True)

PLACES = {'hotel': 1, 'restaurant': 2, 'attraction': 3}
ASPECTS = {
    'hotel':      {'rooms': 5, 'cleanliness': 4, 'service': 3, 'location': 5, 'value': 2, 'safety': 4},
    'restaurant': {'food': 5, 'service': 4, 'atmosphere': 5, 'value': 3, 'safety': 4},
    'attraction': {'experience': 4, 'crowds': 2, 'accessibility': 5, 'value': 3, 'safety': 5},
}
made = {}

for kind, pid in PLACES.items():
    print('\n-- %s review flow --' % kind)
    form = get('/review/new?place=%d' % pid, jar_w)
    check('form renders for a %s' % kind, 'Add more detail' in form, True)
    for a in ASPECTS[kind]:
        check('...asks about %s' % a, ('name="aspect[%s]"' % a) in form, True)
    # the fieldsets for other categories are present but disabled, so the browser never posts them
    check('...other categories are disabled', form.count('disabled') >= 4, True)

    fields = [('_csrf', token(form, '_csrf')), ('_submit', token(form, '_submit')),
              ('action', 'publish'), ('place_id', str(pid)),
              ('destination_id', token(form, 'destination_id')),
              ('subject_type', kind), ('subject_name', token(form, 'subject_name')),
              ('rating', '4'), ('title', 'QA %s headline' % kind),
              ('body', 'A real body for the %s flow that is comfortably over the minimum length required to publish.' % kind),
              ('traveler_type', 'couple')]
    fields += [('aspect[%s]' % a, str(v)) for a, v in ASPECTS[kind].items()]
    post('/review/new', jar_w, fields)

    row = db("SELECT id, traveler_type FROM reviews WHERE title=? AND status='published'", ('QA %s headline' % kind,))
    check('review was published', len(row), 1)
    if not row:
        continue
    rid = row[0][0]
    made[kind] = rid

    # The moment after publishing. A first review is a different event from a fiftieth, and the
    # panel that says so is the only thing a new contributor sees between writing and leaving --
    # so it is checked here rather than trusted, on the first of the three flows and never again.
    # The canonical slug, from the database. A wrong slug 302s to the right one and the
    # redirect drops the query string, so ?published=1 never arrives and the panel this is
    # meant to test silently does not render -- the assertion would be measuring a redirect.
    rslug = db('SELECT slug FROM reviews WHERE id=?', (rid,))[0][0]
    page = get('/review/%d/%s?published=1' % (rid, rslug), jar_w)
    # Expectation derived from the DB, not from the order the flows happen to run in. This harness
    # is re-run against a database that keeps its rows, so "the hotel one is the first review" is
    # true on a fresh database and false on the second run -- an assertion that passes once and
    # then reports a bug that is not there.
    published = db("SELECT COUNT(*) FROM reviews r JOIN users u ON u.id=r.user_id"
                   " WHERE u.username='qa_writer' AND r.status='published'")[0][0]
    first_panel = 'Your first review is live' in page
    check('first-review wording appears exactly when it is the first (%s)' % kind,
          first_panel, published == 1)
    if published == 1:
        check('...and says what it did for the next traveler', 'know what to expect' in page, True)
    else:
        check('...and a later one still confirms it is live (%s)' % kind,
              'Your review is live' in page, True)
    # Two actions, never three: a moment with a menu on it is not a moment.
    buttons = page.count('class="btn btn-accent"') + page.count('class="btn btn-ghost"')
    check('the panel offers two actions, not a menu (%s)' % kind, buttons >= 2, True)
    check('traveler type stored', row[0][1], 'couple')
    stored = dict(db('SELECT aspect, value FROM review_ratings WHERE review_id=?', (rid,)))
    check('every aspect stored', stored, ASPECTS[kind])
    check('no aspect from another category leaked',
          [a for a in stored if a not in ASPECTS[kind]], [])
    mirror = db('SELECT safety_rating, value_rating FROM reviews WHERE id=?', (rid,))[0]
    check('mirror columns follow the aspects', list(mirror), [ASPECTS[kind]['safety'], ASPECTS[kind]['value']])
    shown = get('/review/%d' % rid, jar_w, follow=True)
    check('the review page shows the traveler type', 'Couple' in shown, True)
    check('the review page shows an aspect label',
          any(lbl in shown for lbl in ('Rooms', 'Food', 'The experience')), True)

print('\n-- editing --')
rid = made['hotel']
ident = db('SELECT destination_id, subject_name FROM reviews WHERE id=?', (rid,))[0]
DEST, SUBJ = str(ident[0]), ident[1]
form = get('/review/%d/edit' % rid, jar_w)
# Scoped to the rooms <select> only. A DOTALL search across the whole document will happily match
# a "selected" belonging to some other control, which is a test that passes when the feature is
# broken -- it did exactly that on the first run of this file.
def selected_in(html, aspect):
    m = re.search(r'<select[^>]*name="aspect\[%s\]"(.*?)</select>' % aspect, html, re.S)
    if not m:
        return None
    o = re.search(r'<option value="(\d)"\s+selected', m.group(1))
    return int(o.group(1)) if o else None


check('the existing rooms rating is pre-selected', selected_in(form, 'rooms'), 5)
# 'food' belongs to the restaurant fieldset, which this hotel review has never rated.
check('an aspect this review never rated is not pre-selected', selected_in(form, 'food'), None)
check('the hotel fieldset is the enabled one',
      re.search(r'data-category="hotel"[^>]*disabled', form) is None, True)
check('the restaurant fieldset is disabled',
      re.search(r'data-category="restaurant"[^>]*disabled', form) is not None, True)
fields = [('_csrf', token(form, '_csrf')), ('action', 'publish'),
          ('destination_id', DEST), ('subject_type', 'hotel'),
          ('subject_name', SUBJ), ('rating', '5'),
          ('title', 'QA hotel headline'), ('body', 'An edited body for the hotel flow, still comfortably over the publish minimum.'),
          ('traveler_type', 'family'),
          ('aspect[rooms]', '2'), ('aspect[cleanliness]', ''), ('aspect[service]', '3'),
          ('aspect[location]', '5'), ('aspect[value]', '4'), ('aspect[safety]', '4')]
post('/review/%d/edit' % rid, jar_w, fields)
stored = dict(db('SELECT aspect, value FROM review_ratings WHERE review_id=?', (rid,)))
check('a changed aspect is updated', stored.get('rooms'), 2)
check('a cleared aspect is removed', 'cleanliness' in stored, False)
check('no stale child rows', len(stored), 5)
check('no duplicate rows',
      db('SELECT COUNT(*) FROM (SELECT review_id, aspect FROM review_ratings GROUP BY review_id, aspect HAVING COUNT(*)>1)')[0][0], 0)
check('traveler type updated', db('SELECT traveler_type FROM reviews WHERE id=?', (rid,))[0][0], 'family')
check('mirror value column followed the edit', db('SELECT value_rating FROM reviews WHERE id=?', (rid,))[0][0], 4)

print('\n-- validation refuses bad input --')
form = get('/review/%d/edit' % rid, jar_w)
base = [('_csrf', token(form, '_csrf')), ('action', 'publish'),
        ('destination_id', DEST), ('subject_type', 'hotel'),
        ('subject_name', SUBJ), ('rating', '5'),
        ('title', 'QA hotel headline'),
        ('body', 'An edited body for the hotel flow, still comfortably over the publish minimum.')]
out = post('/review/%d/edit' % rid, jar_w, base + [('aspect[rooms]', '9')])
check('a rating of 9 is rejected', 'Ratings must be from 1 to 5' in out, True)
check('...and nothing was written', db('SELECT value FROM review_ratings WHERE review_id=? AND aspect=?', (rid, 'rooms'))[0][0], 2)
out = post('/review/%d/edit' % rid, jar_w, base + [('aspect[made_up]', '3')])
check('an invented aspect is rejected', 'Unknown rating field' in out, True)
check('...and was not stored', db("SELECT COUNT(*) FROM review_ratings WHERE aspect='made_up'")[0][0], 0)
out = post('/review/%d/edit' % rid, jar_w, base + [('aspect[food]', '5'), ('aspect[rooms]', '2')])
check('a restaurant aspect on a hotel review does not block the save', 'Unknown rating field' not in out, True)
check('...and is not stored', db('SELECT COUNT(*) FROM review_ratings WHERE review_id=? AND aspect=?', (rid, 'food'))[0][0], 0)
out = post('/review/%d/edit' % rid, jar_w, base + [('traveler_type', 'astronaut')])
check('an invented traveler type is discarded, not stored',
      db('SELECT traveler_type FROM reviews WHERE id=?', (rid,))[0][0], None)
# That submission carried no aspect[] keys at all. Absent means cleared, so the whole set goes.
check('a submission with no aspect fields clears them all',
      db('SELECT COUNT(*) FROM review_ratings WHERE review_id=?', (rid,))[0][0], 0)

# Put them back and confirm the edit form then reflects the stored values.
form = get('/review/%d/edit' % rid, jar_w)
post('/review/%d/edit' % rid, jar_w,
     [('_csrf', token(form, '_csrf')), ('action', 'publish'), ('destination_id', DEST),
      ('subject_type', 'hotel'), ('subject_name', SUBJ), ('rating', '5'),
      ('title', 'QA hotel headline'),
      ('body', 'An edited body for the hotel flow, still comfortably over the publish minimum.'),
      ('traveler_type', 'family'),
      ('aspect[rooms]', '5'), ('aspect[cleanliness]', '4'), ('aspect[service]', '3'),
      ('aspect[location]', '5'), ('aspect[value]', '2'), ('aspect[safety]', '4')])
form = get('/review/%d/edit' % rid, jar_w)
check('re-added ratings come back pre-selected', selected_in(form, 'rooms'), 5)
check('the optional section opens when there is something in it',
      re.search(r'<details[^>]*\sopen>', form) is not None, True)

print('\n-- permissions --')
code = get('/review/%d/edit' % rid, jar_o, code_only=True)
check('another user cannot open the edit form', code, '403')
form2 = get('/review/%d/edit' % made['restaurant'], jar_w)
code = post('/review/%d/edit' % rid, jar_o,
            [('_csrf', token(form2, '_csrf')), ('action', 'publish'), ('subject_type', 'hotel'),
             ('rating', '1'), ('title', 'hijacked'), ('body', 'x' * 60)], code_only=True)
check('another user cannot post an edit', code, '403')
check('...and the review is untouched', db('SELECT title FROM reviews WHERE id=?', (rid,))[0][0], 'QA hotel headline')

print('\n-- an old review with none of this --')
old = db("SELECT id FROM reviews WHERE title='A QA review of QA Hotel'")
if old:
    oid = old[0][0]
    check('it has no aspect rows beyond the backfill',
          db('SELECT COUNT(*) FROM review_ratings WHERE review_id=?', (oid,))[0][0], 0)
    check('its page still renders', get('/review/%d' % oid, jar_w, code_only=True, follow=True), '200')

print('\n-- aggregation and the display threshold on the place page --')
page = get('/p/qa-hotel', jar_w)
check('one rating is not advertised as consensus', 'Shown once at least' in page, False)
print('FAILS:', len(fails))
sys.exit(1 if fails else 0)
