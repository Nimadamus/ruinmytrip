"""
Build a ready to post social batch from docs/social/content_bank.json.

For every post it writes:
  * slides as 1080x1350 PNGs (an Instagram carousel, a TikTok photo post, and the first slide works
    as a Facebook image),
  * the Facebook, Instagram and TikTok text, each with its own tracked link,
  * a schedule.csv that a person or the browser automation works through, one row per platform post.

Nothing is posted by this script. It is the part that can run unattended; posting needs the account
owner's session (Meta Business Suite for Facebook and Instagram, TikTok Studio for TikTok) or, later,
an API token he chooses to issue.

  python scripts/social_kit.py --start 2026-09-28 [--days 14] [--out ~/rmt_social]

Tracking: utm_source is the platform, utm_medium post (Facebook, where the link is clickable) or bio
(Instagram and TikTok, where it is not), utm_campaign social_v1, utm_content the post id. The
scorecard's by channel table (/admin/funnel) reads these.
"""
import argparse
import csv
import datetime as dt
import json
import os
import textwrap
from urllib.parse import urlencode

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = 'https://ruinmytrip.com'
W, H = 1080, 1350
INK, BRAND, ACCENT, PAPER = (15, 27, 45), (15, 118, 110), (232, 89, 12), (250, 249, 245)
FONTS = r'C:\Windows\Fonts'
# The one link each profile carries. Set once in the profile; never changed per post.
BIO = {src: f'{SITE}/?utm_source={src}&utm_medium=bio&utm_campaign=profile' for src in ('instagram', 'tiktok')}


def font(name, size):
    try:
        return ImageFont.truetype(os.path.join(FONTS, name), size)
    except OSError:
        return ImageFont.load_default()


def link(path, source, medium, pid, campaign):
    q = urlencode({'utm_source': source, 'utm_medium': medium, 'utm_campaign': campaign, 'utm_content': pid})
    return SITE + path + ('&' if '?' in path else '?') + q


def slide(text, idx, total, pillar, out):
    first = idx == 0
    img = Image.new('RGB', (W, H), INK if first else PAPER)
    d = ImageDraw.Draw(img)
    fg = PAPER if first else INK
    d.rectangle([0, 0, W, 18], fill=BRAND)
    d.text((80, 110), pillar.upper(), font=font('segoeuib.ttf', 34), fill=(125, 211, 200) if first else BRAND)
    size = 96 if first else 78
    body = font('georgiab.ttf', size)
    lines = textwrap.wrap(text, width=16 if first else 20)
    y = (H - len(lines) * int(size * 1.25)) // 2
    for ln in lines:
        d.text((80, y), ln, font=body, fill=fg)
        y += int(size * 1.25)
    foot = font('segoeuib.ttf', 34)
    d.text((80, H - 130), 'ruinmytrip.com', font=foot, fill=ACCENT)
    d.text((W - 80, H - 130), f'{idx + 1}/{total}', font=foot, fill=fg, anchor='ra')
    img.save(out, 'PNG', optimize=True)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--start', required=True, help='first posting day, YYYY-MM-DD, Pacific')
    ap.add_argument('--days', type=int, default=14)
    ap.add_argument('--out', default=os.path.expanduser('~/rmt_social'))
    ap.add_argument('--skip', default='', help='comma separated post ids already used')
    a = ap.parse_args()

    bank = json.load(open(os.path.join(ROOT, 'docs', 'social', 'content_bank.json'), encoding='utf-8'))
    camp = bank['campaign']
    skip = {s for s in a.skip.split(',') if s}
    byid = {p['id']: p for p in bank['posts']}
    cal = bank.get('calendar')
    if cal and a.start == cal['start']:
        # The planned calendar: one post a day, themes spread on purpose.
        posts = [byid[i] for i in cal['days'] if i not in skip]
    else:
        posts = [p for p in bank['posts'] if p['id'] not in skip]
    start = dt.date.fromisoformat(a.start)
    out = os.path.join(a.out, start.isoformat())
    os.makedirs(out, exist_ok=True)

    rows = []
    for i, p in enumerate(posts[:a.days]):
        day = start + dt.timedelta(days=i)
        pdir = os.path.join(out, f'{i + 1:02d}_{p["id"]}')
        os.makedirs(pdir, exist_ok=True)
        for j, s in enumerate(p['slides']):
            slide(s, j, len(p['slides']), p['pillar'], os.path.join(pdir, f'slide{j + 1}.png'))
        fb = p['facebook'] + '\n\n' + link(p['link_path'], 'facebook', 'post', p['id'], camp)
        ig = p['caption'] + ' Link in bio.'
        tt = p['caption'] + ' Link in bio.'
        with open(os.path.join(pdir, 'copy.txt'), 'w', encoding='utf-8') as f:
            f.write(f'FACEBOOK\n{fb}\n\nINSTAGRAM\n{ig}\n\nTIKTOK\n{tt}\n\n'
                    f'Instagram and TikTok allow one link, in the bio, so those two are measured per\n'
                    f'platform rather than per post:\n{BIO["instagram"]}\n{BIO["tiktok"]}\n')
        # Every platform every day, the same slides: Facebook 10:00, Instagram 12:00, TikTok 18:00.
        rows.append([day.isoformat(), '10:00', 'facebook', p['id'], pdir, fb])
        rows.append([day.isoformat(), '12:00', 'instagram', p['id'], pdir, ig])
        rows.append([day.isoformat(), '18:00', 'tiktok', p['id'], pdir, tt])
    with open(os.path.join(out, 'schedule.csv'), 'w', newline='', encoding='utf-8') as f:
        w = csv.writer(f)
        w.writerow(['date_pacific', 'time_pacific', 'platform', 'post_id', 'folder', 'text'])
        w.writerows(rows)
    print(f'{len(rows)} scheduled posts, {min(len(posts), a.days)} sets of slides -> {out}')


if __name__ == '__main__':
    main()
