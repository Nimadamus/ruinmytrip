"""Acquisition scoreboard from production proof, plus /cron/acquire when that route is deployed.

People only. Writes ~/rmt_kpi/ACQUIRE.md. Does not print secrets and does not write to the site.

  python scripts/acquire_board.py [--days 31]
"""
import datetime
import json
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', 'tools', 'rmt_ops'))
sys.path.insert(0, os.path.dirname(__file__))
import dbsnap  # noqa: E402
import rmt_ops as r  # noqa: E402


def main():
    days = 31
    if len(sys.argv) > 1 and sys.argv[1].isdigit():
        days = int(sys.argv[1])
    key = r.env()['CRON_KEY']
    proof = dbsnap.get('/cron/proof?days=%d' % days, key)
    acquire = None
    acquire_note = ''
    try:
        acquire = dbsnap.get('/cron/acquire?days=%d' % days, key)
        if isinstance(acquire, dict) and acquire.get('_text'):
            acquire_note = 'not deployed yet (the route answered, but not with JSON)'
            acquire = None
    except Exception as e:
        acquire_note = type(e).__name__
        acquire = None

    t = proof.get('totals') or {}
    lines = [
        '# RuinMyTrip acquisition scoreboard',
        '',
        'Generated %s Pacific. People numbers are distinct browsers from /cron/proof. '
        'Bots are not added in. Definitions: docs/METRICS.md.' % datetime.datetime.now().strftime('%Y-%m-%d %H:%M'),
        '',
        'Window %s to %s UTC.' % (proof.get('from_utc'), proof.get('to_utc')),
        '',
        '## People',
        '',
        '| measure | n |',
        '|---|---|',
    ]
    for k in ('verified_humans', 'returning_humans', 'search_humans', 'referral_humans',
              'signups', 'confirmed', 'review_opened', 'review_submitted', 'reviews_published',
              'map_users', 'map_saves', 'trips', 'buddy_posts', 'messages', 'active_members'):
        lines.append('| %s | %s |' % (k, t.get(k, '')))
    lines += ['', '## Traffic sources (verified humans)', '',
              '| source | humans | joined | reviewed | used map |',
              '|---|---|---|---|---|']
    for src, row in (proof.get('channels') or {}).items():
        if not isinstance(row, dict):
            continue
        lines.append('| %s | %s | %s | %s | %s |' % (
            src, row.get('humans', 0), row.get('joined', 0), row.get('reviewed', 0), row.get('used_map', 0)))

    lines += ['', '## Outreach attribution', '']
    if not acquire or 'events' not in acquire:
        lines += [
            'Campaign and content attribution is not on production yet%s.' % (
                (': ' + acquire_note) if acquire_note else ''),
            '',
            'The local route `/cron/acquire` counts join_created, profile_completed, review_publish_success, '
            'review_held_for_join, buddy_post_created and trip_created by utm_source, utm_campaign and utm_content. '
            'It does not scan page views. It is tested and not deployed. Until it ships, a registration can be '
            'tied to a source (the joined column above) and not to a specific post.',
        ]
    else:
        lines += [
            'From /cron/acquire. Product counts are table rows. Events are first-touch utm on the action. selfcheck excluded.',
            '',
            '| measure | n |',
            '|---|---|',
        ]
        for k, n in (acquire.get('product') or {}).items():
            lines.append('| %s | %s |' % (k, n))
        lines += ['', '| source | campaign | content | event | n |', '|---|---|---|---|---|']
        ev = acquire.get('events') or []
        if not ev:
            lines.append('| none | | | | 0 |')
        for e in ev:
            lines.append('| %s | %s | %s | %s | %s |' % (
                e.get('source'), e.get('campaign'), e.get('content'), e.get('event'), e.get('n')))

    out = os.path.join(os.path.expanduser('~'), 'rmt_kpi', 'ACQUIRE.md')
    os.makedirs(os.path.dirname(out), exist_ok=True)
    open(out, 'w', encoding='utf-8').write('\n'.join(lines) + '\n')
    print('wrote %s' % out)
    print('\n'.join(lines))


if __name__ == '__main__':
    main()
