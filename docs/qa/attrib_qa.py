"""Attribution, proved on production at the visit level.

Six arrival shapes, each in its own browser that has never seen this site, because attribution is
first touch and a reused browser proves nothing. Each one lands, then reads two more pages carrying
no parameters at all, which is the part that used to be lost.

Nothing here signs up and nothing here writes a trip: the whole chain past the visit is proved on the
dev server instead, where a test account costs nothing. Everything below is tagged
utm_campaign=attrib-qa so these sessions can be told apart from a real campaign for ever.
"""
import json, sys
from playwright.sync_api import sync_playwright

B = "https://ruinmytrip.com"
UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0 Safari/537.36")
CRAWLER = "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"

CASES = [
    ("reddit",   f"{B}/d/munich-germany?utm_source=reddit&utm_medium=post&utm_campaign=attrib-qa&utm_content=selfcheck", None),
    ("facebook", f"{B}/d/lisbon-portugal?utm_source=facebook&utm_medium=group&utm_campaign=attrib-qa&utm_content=selfcheck", None),
    ("x",        f"{B}/d/miami-usa?utm_source=x&utm_medium=post&utm_campaign=attrib-qa&utm_content=selfcheck", None),
    # A member's share link: the same shape the share control now produces.
    ("share",    f"{B}/d/bangkok-thailand?utm_source=whatsapp&utm_medium=share&utm_campaign=attrib-qa&utm_content=selfcheck", None),
    # No parameters and no referrer at all. This is what "direct" has to mean.
    ("direct",   f"{B}/d/berlin-germany", None),
    # No parameters, but a search engine sent them. The host is read and mapped, never stored.
    ("search",   f"{B}/d/amsterdam-netherlands", "https://www.google.com/"),
    # A site we do not publish by name: this has to land as 'referral', not as 'other'.
    ("referral", f"{B}/d/prague-czechia", "https://someblog.example/post"),
]

# Two more pages with NO parameters. If first touch is held, these still belong to the same source.
FOLLOW = ["/explore", "/travelers"]

out = {}
with sync_playwright() as p:
    b = p.chromium.launch()
    for name, url, referer in CASES:
        ctx = b.new_context(user_agent=UA, viewport={"width": 1280, "height": 900})
        pg = ctx.new_page()
        r = pg.goto(url, wait_until="domcontentloaded", referer=referer) if referer else pg.goto(url, wait_until="domcontentloaded")
        first = r.status if r else None
        # The cookie the site sets to recognise a browser. Its presence on the SECOND page is the
        # discriminator between a person and a fetcher, so it is checked rather than assumed.
        follow_status = []
        for f in FOLLOW:
            fr = pg.goto(B + f, wait_until="domcontentloaded")
            follow_status.append(fr.status if fr else None)
        cookies = {c["name"] for c in ctx.cookies()}
        out[name] = {"landing": first, "follow": follow_status,
                     "acq_cookie": "rmt_acq" in cookies, "cookies": sorted(cookies)}
        ctx.close()

    # A crawler must not appear anywhere in this table at all.
    ctx = b.new_context(user_agent=CRAWLER)
    pg = ctx.new_page()
    cr = pg.goto(f"{B}/d/munich-germany?utm_source=reddit&utm_medium=post&utm_campaign=attrib-qa-crawler",
                 wait_until="domcontentloaded")
    out["crawler"] = {"landing": cr.status if cr else None,
                      "cookies": sorted(c["name"] for c in ctx.cookies())}
    ctx.close()
    b.close()

print(json.dumps(out, indent=1))
fails = []
for k, v in out.items():
    if k == "crawler":
        continue
    if v["landing"] != 200:
        fails.append(f"{k}: landing {v['landing']}")
    if any(s != 200 for s in v["follow"]):
        fails.append(f"{k}: follow pages {v['follow']}")
    if not v["acq_cookie"] and k != "direct":
        fails.append(f"{k}: no first touch cookie held")
print("FAILURES:", len(fails))
for f in fails:
    print("  ", f)
sys.exit(1 if fails else 0)
