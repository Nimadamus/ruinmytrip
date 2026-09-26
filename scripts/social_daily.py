"""
The daily social run, for the hidden scheduled task "RMT Social Daily" (07:30 Pacific).

1. social_from_site.py: new post candidates from what happened on the site.
2. social_daily_check.py: the next three days of links, share images, the trip form, tracking.

It posts nothing. Results: ~/rmt_social/site/<date>/ and ~/rmt_social/checks/<date>.json, and one
line per run appended to ~/rmt_social/daily.log.
"""
import datetime as dt
import os
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
LOG = os.path.expanduser('~/rmt_social/daily.log')


def run(name):
    r = subprocess.run([sys.executable, os.path.join(HERE, name)], capture_output=True, text=True, cwd=os.path.dirname(HERE))
    return r.returncode, (r.stdout.strip().splitlines() or [''])[-1] + (' | ' + r.stderr.strip()[-200:] if r.stderr.strip() else '')


def main():
    os.makedirs(os.path.dirname(LOG), exist_ok=True)
    a = run('social_from_site.py')
    b = run('social_daily_check.py')
    line = '%s from_site=%s %s || check=%s %s\n' % (dt.datetime.now().isoformat(timespec='minutes'), a[0], a[1], b[0], b[1])
    open(LOG, 'a', encoding='utf-8').write(line)
    print(line.strip())
    sys.exit(max(a[0], b[0]))


if __name__ == '__main__':
    main()
