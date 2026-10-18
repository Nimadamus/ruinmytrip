"""The outreach ask (2026-10-18): a reader arriving from a post that asked them sees the city question
first, and the ordinary reader sees the page as before.

  python scripts/ci/outreach_ask_e2e.py http://127.0.0.1:8099 database/dev.sqlite

For a blog post with a city and for an occasion page, on a laptop and an iPhone 13:
  - with utm_medium=group the stars come before the h1 (blog) or the hero (occasion), on the first screen;
  - a star opens "How was <city>?" with that rating chosen, and the analytics event carries outreach_rate;
  - without a tag the blog keeps its stars under the byline and the occasion page has no ask.
Writes analytics events to the dev database only; posts nothing.
"""
import sqlite3
import sys

from playwright.sync_api import sync_playwright

BASE, DB = sys.argv[1].rstrip('/'), sys.argv[2]
TAG = '?utm_source=facebook&utm_medium=group&utm_campaign=e2e&utm_content=outreach-ask'


def pages():
    c = sqlite3.connect(DB)
    blog = c.execute("""SELECT b.slug FROM blog_posts b JOIN destinations d ON b.body LIKE '%/d/' || d.slug || '%'
                         WHERE b.status = 'published' ORDER BY b.id LIMIT 1""").fetchone()
    return '/blog/' + blog[0]


def check(pg, path, kind, phone):
    fails = []
    pg.goto(BASE + path + TAG)
    ask = pg.locator('.rate-stars a[data-review-cta=outreach_rate]')
    if ask.count() != 5:
        return ['%s: %d outreach stars' % (path, ask.count())]
    first = pg.locator('h1' if kind == 'blog' else '.dest-hero').first.bounding_box()
    box = ask.first.bounding_box()
    vh = pg.viewport_size['height']
    if not box or box['y'] > first['y']:
        fails.append('%s: ask is not above the %s' % (path, 'title' if kind == 'blog' else 'photo'))
    if box and box['y'] + box['height'] > vh:
        fails.append('%s: ask below the first screen (%.2f screens)' % (path, box['y'] / vh))
    if pg.locator('[data-review-cta=blog_rate]').count():
        fails.append('%s: blog stars shown twice' % path)
    ask.nth(1).click()
    pg.wait_for_url('**/review/new**')
    if not pg.is_checked('#rate-4') or 'How was' not in pg.inner_text('h1'):
        fails.append('%s: form not prefilled' % path)
    pg.goto(BASE + path)
    if pg.locator('[data-review-cta=outreach_rate]').count():
        fails.append('%s: ask shown without a tag' % path)
    if kind == 'blog' and pg.locator('[data-review-cta=blog_rate]').count() != 5:
        fails.append('%s: ordinary blog stars missing' % path)
    return fails


def main():
    blog = pages()
    fails = []
    with sync_playwright() as p:
        b = p.chromium.launch()
        for phone in (False, True):
            ctx = b.new_context(**p.devices['iPhone 13']) if phone else b.new_context(viewport={'width': 1280, 'height': 800})
            pg = ctx.new_page()
            for path, kind in ((blog, 'blog'), ('/e/day-of-the-dead-oaxaca', 'occasion')):
                fails += [('phone ' if phone else 'laptop ') + f for f in check(pg, path, kind, phone)]
            ctx.close()
        b.close()
    n = sqlite3.connect(DB).execute("SELECT COUNT(*) FROM contribution_events WHERE source = 'outreach_rate'").fetchone()[0]
    if n == 0:
        fails.append('no analytics event carries outreach_rate')
    print('\n'.join(fails) if fails else 'ALL PASS (outreach_rate events: %d)' % n)
    sys.exit(1 if fails else 0)


if __name__ == '__main__':
    main()
