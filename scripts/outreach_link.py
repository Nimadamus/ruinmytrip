"""One tagged link per place we post, so every alert and signup names the post that produced it.

    python scripts/outreach_link.py facebook group web-summit lisbon-digital-nomads
    -> https://ruinmytrip.com/e/web-summit-lisbon?utm_source=facebook&utm_medium=group&utm_campaign=web-summit&utm_content=lisbon-digital-nomads

source and medium must be words the site keeps (app/acquisition.php RMT_ACQ_SOURCES / RMT_ACQ_MEDIUMS);
anything else is filed as 'other' and the post can no longer be told apart. content is the one
place the link went: a group, a thread, a creator, a hostel. Lowercase, digits and dashes.
"""
import re, sys
from urllib.parse import urlencode

PAGES = {'day-of-the-dead': '/e/day-of-the-dead-oaxaca', 'yi-peng': '/e/yi-peng-chiang-mai', 'web-summit': '/e/web-summit-lisbon'}
SOURCES = {'reddit', 'facebook', 'instagram', 'tiktok', 'x', 'youtube', 'linkedin', 'pinterest', 'whatsapp', 'telegram',
           'discord', 'email', 'creator', 'newsletter', 'qr', 'other'}
MEDIUMS = {'post', 'comment', 'reply', 'bio', 'story', 'group', 'dm', 'social', 'email', 'other'}

def link(source, medium, campaign, content):
    if source not in SOURCES: sys.exit(f'unknown source {source}: {sorted(SOURCES)}')
    if medium not in MEDIUMS: sys.exit(f'unknown medium {medium}: {sorted(MEDIUMS)}')
    if campaign not in PAGES: sys.exit(f'unknown campaign {campaign}: {sorted(PAGES)}')
    if not re.fullmatch(r'[a-z0-9][a-z0-9\-]{1,39}', content): sys.exit('content: lowercase letters, digits, dashes, max 40')
    return 'https://ruinmytrip.com' + PAGES[campaign] + '?' + urlencode(
        {'utm_source': source, 'utm_medium': medium, 'utm_campaign': campaign, 'utm_content': content})

if __name__ == '__main__':
    if len(sys.argv) != 5: sys.exit(__doc__)
    print(link(*sys.argv[1:]))
