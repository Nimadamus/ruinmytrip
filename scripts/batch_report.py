"""Funnel for one outreach campaign, by placement (utm_content), from /cron/funnel.

    python scripts/batch_report.py day-of-the-dead 14

Reads CRON_KEY from ~/CREDENTIALS.md without printing it. Rows are human sessions only
(the site's own crawler and selfcheck filters apply). The bottleneck column names the first
step where a placement with traffic stops converting.
"""
import json, os, re, sys, urllib.request
camp = sys.argv[1] if len(sys.argv) > 1 else 'day-of-the-dead'
days = sys.argv[2] if len(sys.argv) > 2 else '14'
k = re.search(r'CRON_KEY\W+([A-Za-z0-9]{20,})', open(os.path.expanduser('~/CREDENTIALS.md'), encoding='utf-8').read()).group(1)
d = json.load(urllib.request.urlopen(f'https://ruinmytrip.com/cron/funnel?key={k}&days={days}', timeout=90))
alerts = {(a['source'], a['content']): a for a in d.get('phase', {}).get('alerts_by_channel', []) if a['campaign'] == camp}
rows = [r for r in d.get('acquisition', []) if r.get('campaign') == camp]
def neck(v, a):
    if v['human'] == 0: return 'no traffic'
    if v['alerts'] == 0 and v['signed_up'] == 0: return 'landing: traffic, no alerts'
    if a and a['confirmed'] == 0 and v['alerts'] > 0: return 'email: alerts, none confirmed'
    if v['signed_up'] == 0: return 'account: confirmed, no signup'
    if v['trips'] == 0: return 'onboarding: signup, no trip'
    return 'converting'
print(f'{camp}, last {days} days')
print(f"{'source':10} {'placement':28} {'visits':>6} {'alerts':>6} {'conf':>5} {'signup':>6} {'trips':>5} {'cards':>5}  bottleneck")
for r in rows:
    a = alerts.get((r['source'], r.get('content', '')))
    print(f"{r['source']:10} {r.get('content','') or '-':28} {r['human']:>6} {r['alerts']:>6} {(a or {}).get('confirmed',0):>5} {r['signed_up']:>6} {r['trips']:>5} {r['cards']:>5}  {neck(r, a)}")
ph = d.get('phase', {})
print('site wide: cards', ph.get('cards_created'), 'shared link visits', ph.get('shared_link_visits'), 'signups from cards', ph.get('signups_from_cards'))
