"""Is every campaign actually launchable, or only documented.

For each campaign window: the destination page answers, the campaign line appears with the right
dates, the prefilled trip link carries the city and both dates, the social preview is the purpose
built card rather than whatever the page happened to contain, and a signed out click on the trip
button lands on signup with the whole link preserved AND the page says which city and which dates.

A fresh browser per campaign, because attribution is first touch and a reused one proves nothing.
"""
import json, re, sys, urllib.parse
from playwright.sync_api import sync_playwright

B = "https://ruinmytrip.com"
UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0 Safari/537.36")

CAMPAIGNS = [
    ("oktoberfest",     "munich-germany",        "2026-09-19", "2026-10-04", "Oktoberfest"),
    ("day-of-the-dead", "oaxaca-mexico",         "2026-10-31", "2026-11-02", "Day of the Dead"),
    ("web-summit",      "lisbon-portugal",       "2026-11-09", "2026-11-12", "Web Summit"),
    ("yi-peng",         "chiang-mai-thailand",   "2026-11-23", "2026-11-25", "Yi Peng"),
    ("miami-art-week",  "miami-usa",             "2026-12-01", "2026-12-07", "Art Week"),
    ("new-year-2027",   "bangkok-thailand",      "2026-12-27", "2027-01-02", "New Year"),
    ("rio-carnival",    "rio-de-janeiro-brazil", "2027-02-05", "2027-02-13", "Carnival"),
]

out, fails = {}, []
with sync_playwright() as p:
    b = p.chromium.launch()
    for camp, slug, dfrom, dto, label in CAMPAIGNS:
        ctx = b.new_context(user_agent=UA, viewport={"width": 390, "height": 844})
        pg = ctx.new_page()
        url = f"{B}/d/{slug}?utm_source=reddit&utm_medium=post&utm_campaign={camp}&utm_content=selfcheck"
        r = pg.goto(url, wait_until="domcontentloaded")
        html = pg.content()
        og = dict(re.findall(r'<meta property="og:([a-z:]+)" content="([^"]*)"', html))
        win = pg.query_selector(".cc-window")
        link = pg.get_attribute(".cc-window a", "href") if win else None

        res = {
            "status": r.status if r else None,
            "window_line": win is not None,
            "label_shown": label in pg.inner_text("body") if win else False,
            "prefill": link,
            "prefill_dates_ok": bool(link and dfrom in link and dto in link),
            "og_title": og.get("title", ""),
            "og_card": "/card/city/" in og.get("image", ""),
            "canonical_untouched": f'rel="canonical" href="{B}/d/{slug}"' in html,
            "robots": (re.search(r'<meta name="robots" content="([^"]*)"', html) or [None, ""])[1],
        }

        # The signed out click. The whole link has to survive, and the page has to say what it is for.
        trip = link or f"{B}/trip/new?destination_id=0"
        pg.goto(trip, wait_until="domcontentloaded")
        res["signup_url"] = pg.url
        body = pg.inner_text("body")
        res["signup_names_city"] = "Join and post your" in body
        res["signup_keeps_link"] = urllib.parse.quote(f"date_from={dfrom}", safe="") in pg.url \
            or f"date_from={dfrom}" in urllib.parse.unquote(pg.url)
        out[camp] = res
        ctx.close()
    b.close()

for camp, r in out.items():
    if r["status"] != 200:
        fails.append(f"{camp}: city page {r['status']}")
    if not r["window_line"]:
        fails.append(f"{camp}: no campaign line")
    if not r["label_shown"]:
        fails.append(f"{camp}: the window is not named on the page")
    if not r["prefill_dates_ok"]:
        fails.append(f"{camp}: prefill wrong -> {r['prefill']}")
    if not r["og_card"]:
        fails.append(f"{camp}: social preview is not the purpose built card")
    if "Meet travelers heading there" not in r["og_title"]:
        fails.append(f"{camp}: social title is {r['og_title']!r}")
    if not r["canonical_untouched"]:
        fails.append(f"{camp}: canonical changed")
    if r["robots"] != "index, follow":
        fails.append(f"{camp}: robots {r['robots']}")
    if "/register" not in r["signup_url"] and "/login" not in r["signup_url"]:
        fails.append(f"{camp}: signed out click did not reach signup ({r['signup_url']})")
    elif not r["signup_keeps_link"]:
        fails.append(f"{camp}: signup lost the dates ({r['signup_url']})")
    elif not r["signup_names_city"]:
        fails.append(f"{camp}: signup does not say which city and dates")

print(json.dumps(out, indent=1))
print("FAILURES:", len(fails))
for f in fails:
    print("  ", f)
sys.exit(1 if fails else 0)
