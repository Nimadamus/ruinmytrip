"""
The Facebook feed graphic: one 1200x630 (1.91:1) image per post, composed for the feed.

Why this exists: the 1080x1350 carousel slides were uploaded to Facebook as a four photo post, and
Facebook lays four photos out as one tall crop on the left and three slivers on the right, so the
headline was cut and the options were unreadable (debate_window_aisle, 2026-10-02). Facebook now
gets exactly one image, built here, and never the slides.

Three layouts, picked from the post itself:
  * options: a debate whose follow up slides are each "A or B?" (Settle it: window or aisle...).
    Headline on the left, every choice as an A / OR / B row on the right.
  * versus: a debate whose headline is "A or B?" (Tokyo or Seoul?). Two big sides.
  * statement: everything else. Headline plus the rest of the slides as one sentence.

Every text block is fitted by measured pixel width inside a 64px safe margin, with a minimum size
that stays readable on a phone (Facebook shows this about 400px wide there). A post that cannot fit
at those minimums raises, so a bad graphic is never written. check(path) re-verifies a saved file.

  python scripts/social_fb.py <post_id> [<post_id> ...] --out <dir>   (from content_bank.json)
"""
import json
import os
import re

from PIL import Image, ImageDraw, ImageFont

W, H = 1200, 630
SAFE = 64
INK, INK2, BRAND, TEAL, ACCENT, PAPER = (15, 27, 45), (27, 44, 70), (15, 118, 110), (125, 211, 200), (232, 89, 12), (250, 249, 245)
MUTED = (178, 190, 205)
FONTS = r'C:\Windows\Fonts'
MIN_HEAD, MIN_BODY = 56, 30


class FitError(ValueError):
    pass


def font(name, size):
    return ImageFont.truetype(os.path.join(FONTS, name), size)


def wrap(d, text, f, max_w):
    lines, cur = [], ''
    for word in text.split():
        t = (cur + ' ' + word).strip()
        if d.textlength(t, font=f) <= max_w or not cur:
            cur = t
        else:
            lines.append(cur)
            cur = word
    if cur:
        lines.append(cur)
    return lines


def fit(d, text, name, box_w, box_h, hi, lo, lead=1.12, max_lines=4, one_line_min=None):
    """Largest size from hi down to lo at which text wraps inside box_w x box_h. Raises if none.
    one_line_min: first try to keep the text on one line at that size or larger."""
    if one_line_min:
        try:
            return fit(d, text, name, box_w, box_h, hi, one_line_min, lead, 1)
        except FitError:
            pass
    for size in range(hi, lo - 1, -2):
        f = font(name, size)
        lines = wrap(d, text, f, box_w)
        lh = int(size * lead)
        if len(lines) <= max_lines and len(lines) * lh <= box_h and all(d.textlength(ln, font=f) <= box_w for ln in lines):
            return f, lines, lh
    raise FitError('cannot fit %r in %dx%d at %dpx or more' % (text, box_w, box_h, lo))


def draw_lines(d, x, y, lines, f, lh, fill, boxes, anchor='la'):
    for ln in lines:
        d.text((x, y), ln, font=f, fill=fill, anchor=anchor)
        boxes.append(d.textbbox((x, y), ln, font=f, anchor=anchor))
        y += lh
    return y


def split_or(text):
    m = re.match(r'^\s*(.+?)\s+or\s+(.+?)\?\s*$', text, re.I)
    if not m:
        return None
    a, b = m.group(1).strip(), m.group(2).strip()
    return a[0].upper() + a[1:], b[0].upper() + b[1:]


def layout_of(post):
    slides = post['slides']
    if 'debate' in post['pillar']:
        opts = [split_or(s) for s in slides[1:]]
        if len(opts) >= 2 and all(opts):
            return 'options', opts
        if split_or(slides[0]):
            return 'versus', split_or(slides[0])
    return 'statement', None


def frame(d, pillar, boxes):
    d.rectangle([0, 0, W, 12], fill=BRAND)
    f = font('segoeuib.ttf', 26)
    d.text((SAFE, SAFE - 6), pillar.upper(), font=f, fill=TEAL)
    boxes.append(d.textbbox((SAFE, SAFE - 6), pillar.upper(), font=f))
    f = font('segoeuib.ttf', 28)
    y = H - SAFE - 42
    d.text((SAFE, y), 'ruinmytrip.com', font=f, fill=ACCENT)
    boxes.append(d.textbbox((SAFE, y), 'ruinmytrip.com', font=f))
    return SAFE + 40, y - 20  # content top, content bottom


def render(post, out):
    img = Image.new('RGB', (W, H), INK)
    d = ImageDraw.Draw(img)
    boxes = []
    top, bottom = frame(d, post['pillar'], boxes)
    kind, data = layout_of(post)
    slides = post['slides']

    if kind == 'options':
        # Left: the headline and the ask. Right: one row per choice, A  OR  B.
        lw = 400
        f, lines, lh = fit(d, slides[0], 'georgiab.ttf', lw, 230, 104, MIN_HEAD, max_lines=2, one_line_min=80)
        ask = 'Once and for all. Pick a side on each one and tell us in the comments.'
        bf, blines, blh = fit(d, ask, 'segoeui.ttf', lw, 170, 34, MIN_BODY, lead=1.3, max_lines=4)
        block = len(lines) * lh + 28 + len(blines) * blh
        y = top + (bottom - top - block) // 2
        y = draw_lines(d, SAFE, y, lines, f, lh, PAPER, boxes) + 28
        draw_lines(d, SAFE, y, blines, bf, blh, MUTED, boxes)
        rx, rw = SAFE + lw + 40, W - SAFE - (SAFE + lw + 40)
        gap = 18
        rh = (bottom - top - gap * (len(data) - 1)) // len(data)
        if rh < 90:
            raise FitError('%d options leave rows %dpx tall' % (len(data), rh))
        ow = 64  # the OR badge
        cw = (rw - ow) // 2
        for i, (a, b) in enumerate(data):
            ry = top + i * (rh + gap)
            d.rounded_rectangle([rx, ry, rx + rw, ry + rh], radius=18, fill=INK2)
            for j, side in enumerate((a, b)):
                cx = rx + cw // 2 + j * (cw + ow)
                sf, sl, slh = fit(d, side, 'segoeuib.ttf', cw - 36, rh - 24, 44, MIN_BODY, lead=1.08, max_lines=2)
                sy = ry + (rh - len(sl) * slh) // 2 + slh // 2
                draw_lines(d, cx, sy, sl, sf, slh, PAPER, boxes, anchor='mm')
            bx, by = rx + cw + ow // 2, ry + rh // 2
            d.ellipse([bx - 28, by - 28, bx + 28, by + 28], fill=ACCENT)
            of = font('segoeuib.ttf', 22)
            d.text((bx, by), 'OR', font=of, fill=PAPER, anchor='mm')

    elif kind == 'versus':
        a, b = data
        sub = ' '.join(slides[1:])
        bf, blines, blh = fit(d, sub, 'segoeui.ttf', W - 2 * SAFE, 90, 34, MIN_BODY, lead=1.25, max_lines=2)
        sub_h = len(blines) * blh
        ph = bottom - top - sub_h - 24
        cw = (W - 2 * SAFE - 80) // 2
        for j, side in enumerate((a, b)):
            x0 = SAFE + j * (cw + 80)
            d.rounded_rectangle([x0, top, x0 + cw, top + ph], radius=22, fill=INK2 if j == 0 else BRAND)
            sf, sl, slh = fit(d, side, 'georgiab.ttf', cw - 60, ph - 40, 110, MIN_HEAD, max_lines=2)
            sy = top + (ph - len(sl) * slh) // 2 + slh // 2
            draw_lines(d, x0 + cw // 2, sy, sl, sf, slh, PAPER, boxes, anchor='mm')
        bx, by = W // 2, top + ph // 2
        d.ellipse([bx - 40, by - 40, bx + 40, by + 40], fill=ACCENT)
        of = font('segoeuib.ttf', 30)
        d.text((bx, by), 'OR', font=of, fill=PAPER, anchor='mm')
        draw_lines(d, SAFE, top + ph + 24, blines, bf, blh, MUTED, boxes)

    else:
        # Slides split sentences across cards ("The airline fee" / "that made you angriest."), so a
        # slide that starts in lower case belongs to the headline before it.
        k = 1
        while k < len(slides) and slides[k][:1].islower():
            k += 1
        head = ' '.join(slides[:k])
        body = ' '.join(slides[k:])
        f, lines, lh = fit(d, head, 'georgiab.ttf', W - 2 * SAFE, 230, 96, MIN_HEAD, max_lines=2, one_line_min=72)
        bf, blines, blh = fit(d, body, 'segoeui.ttf', W - 2 * SAFE - 40, 180, 44, MIN_BODY, lead=1.3, max_lines=3)
        block = len(lines) * lh + 32 + len(blines) * blh
        y = top + (bottom - top - block) // 2
        y = draw_lines(d, SAFE, y, lines, f, lh, PAPER, boxes) + 32
        d.rectangle([SAFE, y + 6, SAFE + 8, y + len(blines) * blh - 10], fill=ACCENT)
        draw_lines(d, SAFE + 32, y, blines, bf, blh, PAPER, boxes)

    for x0, y0, x1, y1 in boxes:
        if x0 < SAFE - 4 or y0 < SAFE - 4 or x1 > W - SAFE + 4 or y1 > H - SAFE + 4:  # 4px for glyph overhang
            raise FitError('text box %s outside the safe area' % ((x0, y0, x1, y1),))
    img.save(out, 'PNG', optimize=True)
    return kind


def check(path):
    """Problems with a saved Facebook graphic; an empty list means it is fine to post."""
    if not os.path.exists(path):
        return ['missing ' + path]
    with Image.open(path) as im:
        return [] if im.size == (W, H) else ['%s is %dx%d, not %dx%d' % (path, im.size[0], im.size[1], W, H)]


if __name__ == '__main__':
    import argparse
    ap = argparse.ArgumentParser()
    ap.add_argument('ids', nargs='*')
    ap.add_argument('--out', required=True)
    a = ap.parse_args()
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    bank = json.load(open(os.path.join(root, 'docs', 'social', 'content_bank.json'), encoding='utf-8'))
    os.makedirs(a.out, exist_ok=True)
    for p in bank['posts']:
        if not a.ids or p['id'] in a.ids:
            print(p['id'], render(p, os.path.join(a.out, p['id'] + '.png')))
