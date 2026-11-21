"""SEO opportunity engine: snapshot Search Console, score opportunities, track changes, report weekly.

  python scripts/seo_engine.py snapshot [--days 10]   store daily query x page x device rows (idempotent)
  python scripts/seo_engine.py report                  write the weekly report (runs snapshot first)

Store: ~/rmt_research/seo_engine/gsc.sqlite (outside the repo). Reports: ~/rmt_research/seo_engine/reports/.
Read only everywhere: Search Console API with the property's service account, prod DB through a
read-only psql transaction over ssh. Nothing here changes the site.

Scoring, per query+page over the last 28 days (impressions >= 5, position <= 30):
  potential = impressions x expected_ctr(position 3 places better) - clicks
  action    = 'snippet'   position <= 10 and CTR under half of expected (the page ranks, the listing is not chosen)
              'deepen'    position 11-30 (a better page can reach the first screen)
              'gap'       landing on a listing page (/, /explore, /blog ...) = no page written for it
  suspect   = 100+ impressions, 95%+ desktop (over 50+ device-split rows), 0 clicks: looks like automated rank checking. Kept out of
              the scored list and the 'clean' totals until a human SERP check says otherwise.
Change tracking: every database/meta_tests.json entry is compared 28 days before vs after its start.
"""
import argparse
import datetime as dt
import json
import os
import sqlite3
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import gsc_report as g  # noqa: E402

HOME = os.path.expanduser('~')
STORE = os.path.join(HOME, 'rmt_research', 'seo_engine')
DB = os.path.join(STORE, 'gsc.sqlite')
REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SSH = ['ssh', '-o', 'ConnectTimeout=15', '-o', 'BatchMode=yes', '-i', os.path.join(HOME, '.ssh', 'wd_hetzner_ed25519'),
       'root@137.220.40.95']
LISTING = set(g.LISTING_PATHS)
CTR = {1: .28, 2: .15, 3: .10, 4: .07, 5: .05, 6: .04, 7: .03, 8: .025, 9: .02, 10: .018}


def exp_ctr(pos):
    p = max(1, round(pos))
    return CTR.get(p, .01 if p <= 20 else .004 if p <= 30 else .001)


def db():
    os.makedirs(STORE, exist_ok=True)
    c = sqlite3.connect(DB)
    c.execute('CREATE TABLE IF NOT EXISTS rows (date TEXT, query TEXT, page TEXT, device TEXT, '
              'clicks INT, impressions INT, position REAL, PRIMARY KEY (date, query, page, device))')
    c.execute('CREATE TABLE IF NOT EXISTS page_day (date TEXT, page TEXT, clicks INT, '
              'impressions INT, position REAL, PRIMARY KEY (date, page))')
    c.execute('CREATE TABLE IF NOT EXISTS page_dev (date TEXT, page TEXT, device TEXT, clicks INT, '
              'impressions INT, position REAL, PRIMARY KEY (date, page, device))')
    return c


def snapshot(days):
    """Query rows hide anonymised queries, so page totals are stored separately (page_day). Adding the
    device dimension also drops anonymised rows (seen 11/21: SF tax note 219 by page, 4 by page+device),
    so page_dev is only used for the desktop share, never for totals."""
    tok, c = g.token(), db()
    end = dt.date.today() - dt.timedelta(days=2)
    for i in range(days):
        d = str(end - dt.timedelta(days=i))
        for dims, table in ((['query', 'page', 'device'], 'rows'), (['page'], 'page_day'), (['page', 'device'], 'page_dev')):
            rows = g.query(tok, d, d, dims, limit=5000)
            c.execute(f'DELETE FROM {table} WHERE date = ?', (d,))
            for r in rows:
                k = r['keys']
                c.execute(f'INSERT INTO {table} VALUES (?{",?" * (len(k) + 3)})',
                          (d, *k, r['clicks'], r['impressions'], r['position']))
    c.commit()
    return str(end)


def path(u):
    return u.replace('https://ruinmytrip.com', '') or '/'


def window(c, end, days, table='page_day'):
    start = str(dt.date.fromisoformat(end) - dt.timedelta(days=days - 1))
    return start, end


def suspects(c, start, end):
    out = []
    for page, imp, clk, desk, devn in c.execute(
            "SELECT d.page, SUM(d.impressions), SUM(d.clicks), "
            "(SELECT SUM(CASE WHEN device='DESKTOP' THEN impressions ELSE 0 END) * 1.0 / MAX(SUM(impressions), 1) "
            " FROM page_dev v WHERE v.page = d.page AND v.date BETWEEN ? AND ?), "
            "(SELECT SUM(impressions) FROM page_dev v WHERE v.page = d.page AND v.date BETWEEN ? AND ?) "
            "FROM page_day d WHERE d.date BETWEEN ? AND ? GROUP BY d.page", (start, end, start, end, start, end)):
        # The device split only covers non-anonymised rows; judge it on 50+ of them, never on a handful.
        if imp >= 100 and clk == 0 and (devn or 0) >= 50 and (desk or 0) >= .95:
            out.append((page, imp, desk))
    return out


def totals(c, start, end, skip=()):
    q = 'SELECT SUM(impressions), SUM(clicks), SUM(position*impressions)/MAX(SUM(impressions),1) FROM page_day ' \
        'WHERE date BETWEEN ? AND ?' + ''.join(' AND page <> ?' for _ in skip)
    imp, clk, pos = c.execute(q, (start, end, *skip)).fetchone()
    return imp or 0, clk or 0, pos or 0


def opportunities(c, start, end, skip):
    rows = c.execute('SELECT query, page, SUM(clicks), SUM(impressions), SUM(position*impressions)/SUM(impressions) '
                     'FROM rows WHERE date BETWEEN ? AND ? GROUP BY query, page', (start, end)).fetchall()
    out = []
    for q, page, clk, imp, pos in rows:
        if imp < 5 or pos > 30 or page in skip:
            continue
        ctr = clk / imp
        if path(page).rstrip('/') in LISTING or path(page) == '/':
            action = 'gap'
        elif pos <= 10:
            if ctr >= exp_ctr(pos) / 2:
                continue
            action = 'snippet'
        else:
            action = 'deepen'
        potential = imp * exp_ctr(max(1, pos - 3)) - clk
        out.append((round(potential, 1), action, q, path(page), imp, clk, round(pos, 1)))
    # Page level too: most queries on small sites are anonymised, so a page can rank with no query rows.
    for page, clk, imp, pos in c.execute('SELECT page, SUM(clicks), SUM(impressions), SUM(position*impressions)/SUM(impressions) '
                                         'FROM page_day WHERE date BETWEEN ? AND ? GROUP BY page', (start, end)).fetchall():
        if imp < 30 or pos > 10 or page in skip or clk / imp >= exp_ctr(pos) / 2:
            continue
        out.append((round(imp * exp_ctr(max(1, pos - 3)) - clk, 1), 'snippet (page)', '(all queries)', path(page),
                    imp, clk, round(pos, 1)))
    return sorted(out, reverse=True)


def meta_tests(c):
    tests = json.load(open(os.path.join(REPO, 'database', 'meta_tests.json'), encoding='utf-8'))['tests']
    out = []
    last = c.execute('SELECT MAX(date) FROM page_day').fetchone()[0]
    for p, t in tests.items():
        s = dt.date.fromisoformat(t['start'])
        b = c.execute('SELECT SUM(impressions), SUM(clicks) FROM page_day WHERE page = ? AND date BETWEEN ? AND ?',
                      ('https://ruinmytrip.com' + p, str(s - dt.timedelta(days=28)), str(s - dt.timedelta(days=1)))).fetchone()
        a = c.execute('SELECT SUM(impressions), SUM(clicks) FROM page_day WHERE page = ? AND date BETWEEN ? AND ?',
                      ('https://ruinmytrip.com' + p, str(s), str(s + dt.timedelta(days=27)))).fetchone()
        days_after = (dt.date.fromisoformat(last) - s).days + 1 if last else 0
        out.append((p, t['start'], b[0] or 0, b[1] or 0, a[0] or 0, a[1] or 0, max(0, min(28, days_after))))
    return out


FUNNEL_SQL = """set default_transaction_read_only=on;
select 'humans_7d', count(distinct visitor) from contribution_events where event='human_interaction' and created_at::timestamptz > now()-interval '7 days';
select 'humans_search_7d', count(distinct visitor) from contribution_events where event='human_interaction' and acq_source='search' and created_at::timestamptz > now()-interval '7 days';
select 'returning_30d', count(*) from (select visitor from contribution_events where event='human_interaction' and created_at::timestamptz > now()-interval '30 days' group by 1 having count(distinct created_at::timestamptz::date)>1) x;
select 'join_submit_7d', count(*) from contribution_events where event='join_submit' and created_at::timestamptz > now()-interval '7 days';
select 'join_created_7d', count(*) from contribution_events where event='join_created' and created_at::timestamptz > now()-interval '7 days';
select 'join_confirmed_7d', count(*) from contribution_events where event='join_confirmed' and created_at::timestamptz > now()-interval '7 days';
select 'checklist_ticked_7d', count(*) from contribution_events where event='checklist_ticked' and created_at::timestamptz > now()-interval '7 days';
select 'alert_signups_total', count(*) from byg_signups;
select 'users_total', count(*) from users;
"""


def funnel():
    """Human-only numbers from prod, read-only. Bot events (landing_view, join_view) are never used."""
    remote = 'U=$(docker exec srv-ruinmytrip-1 printenv DATABASE_URL); psql "$U" -At -F"|" -f -'
    try:
        r = subprocess.run(SSH + [remote], input=FUNNEL_SQL, capture_output=True, text=True, timeout=90,
                           creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    except Exception as e:  # noqa: BLE001
        return {'error': str(e)}
    out = {}
    for line in r.stdout.splitlines():
        if '|' in line:
            k, v = line.split('|', 1)
            out[k] = v
    if not out:
        out['error'] = (r.stderr or 'no output').strip()[:200]
    return out


def report():
    end = snapshot(10)
    c = db()
    s7, e7 = window(c, end, 7)
    s28, e28 = window(c, end, 28)
    sus = suspects(c, s28, e28)
    skip = tuple(p for p, _, _ in sus)
    pt = dt.datetime.now(dt.timezone(dt.timedelta(hours=-8))).strftime('%Y-%m-%d %I:%M %p PST')
    L = [f'# RuinMyTrip SEO weekly, data through {end} (made {pt})', '',
         'Search Console rows arrive 2 to 3 days late. History is only as deep as the snapshots taken.', '',
         '## Search', '', '| Window | Impressions | Clicks | CTR | Avg pos |', '|---|---|---|---|---|']
    for label, (s, e), sk in (('7d all', (s7, e7), ()), ('7d clean', (s7, e7), skip),
                              ('28d all', (s28, e28), ()), ('28d clean', (s28, e28), skip)):
        i, k, p = totals(c, s, e, sk)
        L.append(f'| {label} | {i:,} | {k} | {k / i * 100 if i else 0:.2f}% | {p:.1f} |')
    L += ['', '"clean" leaves out the suspect pages below.', '']
    if sus:
        L += ['## Suspect impressions (100+, 95%+ desktop, 0 clicks)', '']
        L += [f'- {path(p)}: {i:,} impressions, {d * 100:.0f}% desktop' for p, i, d in sus]
        L.append('')
    L += ['## Top landing pages, 28d', '', '| Page | Impr | Clicks | Pos |', '|---|---|---|---|']
    for p, i, k, pos in c.execute('SELECT page, SUM(impressions) i, SUM(clicks), SUM(position*impressions)/SUM(impressions) '
                                  'FROM page_day WHERE date BETWEEN ? AND ? GROUP BY page ORDER BY i DESC LIMIT 15', (s28, e28)):
        L.append(f'| {path(p)} | {i} | {k} | {pos:.1f} |')
    L += ['', '## Opportunities, 28d (potential = extra clicks if 3 places higher)', '',
          '| Potential | Action | Query | Page | Impr | Clicks | Pos |', '|---|---|---|---|---|---|---|']
    for o in opportunities(c, s28, e28, skip)[:25]:
        L.append('| ' + ' | '.join(str(x) for x in o) + ' |')
    L += ['', '## Snippet and title tests (28 days before vs after start)', '',
          '| Page | Start | Before impr | Before clicks | After impr | After clicks | Days after |', '|---|---|---|---|---|---|---|']
    for t in meta_tests(c):
        L.append('| ' + ' | '.join(str(x) for x in t) + ' |')
    f = funnel()
    L += ['', '## On site, humans only (prod, read-only)', '']
    L += [f'- {k}: {v}' for k, v in f.items()]
    L += ['', 'Users total includes house and test accounts; real outside members are counted by hand.']
    os.makedirs(os.path.join(STORE, 'reports'), exist_ok=True)
    text = '\n'.join(L) + '\n'
    for name in (f'{end}.md', 'latest.md'):
        with open(os.path.join(STORE, 'reports', name), 'w', encoding='utf-8') as fh:
            fh.write(text)
    print(os.path.join(STORE, 'reports', 'latest.md'))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('cmd', choices=['snapshot', 'report'])
    ap.add_argument('--days', type=int, default=10)
    a = ap.parse_args()
    if a.cmd == 'snapshot':
        print(snapshot(a.days))
    else:
        report()


if __name__ == '__main__':
    main()
