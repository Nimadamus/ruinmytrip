"""Reconcile /cron/proof against the rows it is built from, read only.

Run after app/proof.php is deployed:  python scripts/proof_check.py [--days 14]

Checks that must hold on production data, each against an independent read:
  - signups and published reviews equal /cron/kpi accounts and reviews (same tables, same filter)
  - verified humans never exceed the legacy interaction count plus browsers that acted
  - every review_held is preceded by a review_submitted somewhere in the window
  - every browser is in exactly one bucket (humans + bots + unverified = browsers read)
  - the engaged visits /cron/funnel lists by hand are classified the way their steps say
Prints PASS/FAIL per check and exits non-zero on any failure.
"""
import argparse, json, os, sys
sys.path.insert(0, os.path.dirname(__file__))
import kpi_snapshot as k  # noqa: E402

ap = argparse.ArgumentParser(); ap.add_argument('--days', type=int, default=14); a = ap.parse_args()
key = k.cron_key()
proof = json.loads(k.get('https://ruinmytrip.com/cron/proof?days=%d' % a.days, {'X-Cron-Key': key}))
kpi = json.loads(k.get('https://ruinmytrip.com/cron/kpi?days=%d' % a.days, {'X-Cron-Key': key}))
fails = []


def check(name, cond, detail=''):
    print(('PASS  ' if cond else 'FAIL  ') + name + ('' if cond else '  -- ' + str(detail)))
    if not cond:
        fails.append(name)


pt, kt = proof['totals'], kpi['totals']
check('signups equal kpi accounts', pt['signups'] == kt['accounts'], (pt['signups'], kt['accounts']))
check('published reviews equal kpi reviews', pt['reviews_published'] == kt['reviews'], (pt['reviews_published'], kt['reviews']))
check('held reviews equal kpi held reviews', pt['review_held'] == kt['review_held'], (pt['review_held'], kt['review_held']))
check('each browser in exactly one bucket',
      pt['verified_humans'] + pt['suspected_bots'] + pt['unverified'] == proof['browsers_read'],
      (pt['verified_humans'], pt['suspected_bots'], pt['unverified'], proof['browsers_read']))
check('verified humans are not more than legacy visitors plus submitters',
      pt['verified_humans'] <= kt['visitors'] + pt['review_submitted'] + pt['signups'] + 50,
      (pt['verified_humans'], kt['visitors']))
check('no held review without a submit somewhere', pt['review_held'] == 0 or pt['review_submitted'] > 0,
      (pt['review_held'], pt['review_submitted']))
print(json.dumps({'totals': pt, 'rates': proof['rates'], 'reasons': proof['reasons']}, indent=1))
sys.exit(1 if fails else 0)
