"""
Photo led Instagram carousel (4 slides, 1080x1350) from one spec file. Nothing is posted.

  python scripts/ig_carousel.py <spec.json> <outdir>

Spec: photo (path), credit, focus (0..1, horizontal crop centre), kicker, headline, sub,
fact_slides: [{kicker, rows: [[figure, label], ...], notes: [text, ...]}], question, invite.
Photos are the licensed destination heroes the site already credits; the credit goes on the cover
and the last slide.
"""
import json
import os
import sys
import textwrap

from PIL import Image, ImageDraw, ImageFilter, ImageFont

W, H = 1080, 1350
INK, BRAND, ACCENT, PAPER, TEAL = (15, 27, 45), (15, 118, 110), (232, 89, 12), (250, 249, 245), (125, 211, 200)
MUTED = (88, 98, 112)
FONTS = r'C:\Windows\Fonts'
M = 80


def font(name, size):
    return ImageFont.truetype(os.path.join(FONTS, name), size)


def cover(img, w, h, focus=0.5):
    s = max(w / img.width, h / img.height)
    im = img.resize((round(img.width * s), round(img.height * s)), Image.LANCZOS)
    x = int((im.width - w) * focus)
    y = (im.height - h) // 2
    return im.crop((x, y, x + w, y + h))


def wrap(d, text, f, width):
    words, lines, cur = text.split(), [], ''
    for wd in words:
        t = (cur + ' ' + wd).strip()
        if d.textlength(t, font=f) <= width:
            cur = t
        else:
            lines.append(cur)
            cur = wd
    if cur:
        lines.append(cur)
    return lines


def shade(img, start, alpha_max=235):
    """Dark gradient from `start` (0..1 of height) to the bottom, so white text reads on any photo."""
    g = Image.new('L', (1, H))
    for y in range(H):
        t = max(0.0, (y / H - start) / (1 - start))
        g.putpixel((0, y), int(alpha_max * min(1.0, t * 1.4)))
    g = g.resize((W, H))
    ov = Image.new('RGB', (W, H), INK)
    return Image.composite(ov, img, g)


def footer(d, idx, total, dark):
    f = font('segoeuib.ttf', 32)
    d.text((M, H - 92), 'ruinmytrip.com', font=f, fill=ACCENT)
    d.text((W - M, H - 92), f'{idx}/{total}', font=f, fill=PAPER if dark else INK, anchor='ra')


def pill(d, x, y, text, fg, bg):
    f = font('segoeuib.ttf', 30)
    tw = d.textlength(text, font=f)
    d.rounded_rectangle([x, y, x + tw + 44, y + 58], radius=29, fill=bg)
    d.text((x + 22, y + 29), text, font=f, fill=fg, anchor='lm')


def slide_cover(spec, photo, total):
    # Blurred, darkened photo fills the frame; the sharp photo sits as a band at no more than its own
    # resolution, so nothing is blown up past the source.
    bg = cover(photo, W, H, spec.get('focus', 0.5)).filter(ImageFilter.GaussianBlur(18))
    img = Image.blend(bg, Image.new('RGB', (W, H), INK), 0.78)
    band_h = min(560, round(photo.height * W / photo.width))
    band = cover(photo, W, band_h, spec.get('focus', 0.5))
    top = 170
    img.paste(band, (0, top))
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, W, 14], fill=BRAND)
    pill(d, M, 70, spec['kicker'], INK, TEAL)
    d.text((W - M, top + band_h + 14), spec['credit'], font=font('segoeui.ttf', 22), fill=(203, 213, 225), anchor='ra')
    hf = font('georgiab.ttf', 84)
    lines = wrap(d, spec['headline'], hf, W - 2 * M)
    sf = font('segoeui.ttf', 38)
    sub = wrap(d, spec['sub'], sf, W - 2 * M)
    block = len(lines) * 98 + 24 + len(sub) * 52
    area_top, area_bot = top + band_h + 56, H - 130
    if block > area_bot - area_top:
        raise SystemExit(f'cover overflows by {block - (area_bot - area_top)} px')
    y = area_top + (area_bot - area_top - block) // 2
    for ln in lines:
        d.text((M, y), ln, font=hf, fill=PAPER)
        y += 98
    y += 24
    for ln in sub:
        d.text((M, y), ln, font=sf, fill=TEAL)
        y += 52
    footer(d, 1, total, True)
    return img


def slide_facts(spec, fs, photo, idx, total, focus):
    img = Image.new('RGB', (W, H), PAPER)
    img.paste(cover(photo, W, 300, focus), (0, 0))
    d = ImageDraw.Draw(img)
    d.rectangle([0, 300, W, 312], fill=BRAND)
    ff, lf, nf = font('segoeuib.ttf', 92), font('segoeui.ttf', 38), font('segoeui.ttf', 36)
    items = []  # (kind, payload, height)
    items.append(('kicker', fs['kicker'], 80))
    for fig, label in fs.get('rows', []):
        lab = wrap(d, label, lf, W - 2 * M)
        items.append(('row', (fig, lab), 112 + len(lab) * 50 + 40))
    for note in fs.get('notes', []):
        lines = wrap(d, note, nf, W - 2 * M - 40)
        items.append(('note', lines, len(lines) * 48 + 28))
    total_h = sum(h for _, _, h in items)
    area_top, area_bot = 360, H - 140
    if total_h > area_bot - area_top:
        raise SystemExit(f'slide {idx} overflows by {total_h - (area_bot - area_top)} px')
    y = area_top + (area_bot - area_top - total_h) // 2
    for kind, pay, h in items:
        if kind == 'kicker':
            d.text((M, y), pay, font=font('segoeuib.ttf', 32), fill=BRAND)
        elif kind == 'row':
            fig, lab = pay
            d.text((M, y), fig, font=ff, fill=ACCENT)
            yy = y + 112
            for ln in lab:
                d.text((M, yy), ln, font=lf, fill=INK)
                yy += 50
        else:
            d.rectangle([M, y + 8, M + 8, y + len(pay) * 48 - 4], fill=TEAL)
            for i, ln in enumerate(pay):
                d.text((M + 40, y + i * 48), ln, font=nf, fill=INK)
        y += h
    footer(d, idx, total, False)
    return img


def slide_ask(spec, photo, total):
    bg = cover(photo, W, H, spec.get('focus', 0.5)).filter(ImageFilter.GaussianBlur(14))
    img = Image.blend(bg, Image.new('RGB', (W, H), INK), 0.72)
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, W, 14], fill=BRAND)
    pill(d, M, 70, 'YOUR TURN', INK, TEAL)
    qf = font('georgiab.ttf', 76)
    q = wrap(d, spec['question'], qf, W - 2 * M)
    inv_f = font('segoeui.ttf', 40)
    inv = wrap(d, spec['invite'], inv_f, W - 2 * M)
    block = len(q) * 94 + 60 + len(inv) * 56
    y = (H - block) // 2
    for ln in q:
        d.text((M, y), ln, font=qf, fill=PAPER)
        y += 94
    y += 60
    for ln in inv:
        d.text((M, y), ln, font=inv_f, fill=TEAL)
        y += 56
    d.text((W - M, H - 140), spec['credit'], font=font('segoeui.ttf', 22), fill=(203, 213, 225), anchor='ra')
    footer(d, total, total, True)
    return img


def main():
    spec = json.load(open(sys.argv[1], encoding='utf-8'))
    out = sys.argv[2]
    os.makedirs(out, exist_ok=True)
    photo = Image.open(spec['photo']).convert('RGB')
    total = 2 + len(spec['fact_slides'])
    slides = [slide_cover(spec, photo, total)]
    for i, fs in enumerate(spec['fact_slides']):
        slides.append(slide_facts(spec, fs, photo, i + 2, total, fs.get('focus', 0.5)))
    slides.append(slide_ask(spec, photo, total))
    for i, s in enumerate(slides, 1):
        s.save(os.path.join(out, f'slide{i}.png'), 'PNG', optimize=True)
    sheet = Image.new('RGB', (4 * 270 + 50, 338 + 20), 'white')
    for i, s in enumerate(slides):
        sheet.paste(s.resize((270, 338), Image.LANCZOS), (10 + i * 280, 10))
    sheet.save(os.path.join(out, 'phone_sheet.png'))
    print('ok', out, len(slides))


if __name__ == '__main__':
    main()
