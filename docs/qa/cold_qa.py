"""Cold visitor QA: can a stranger arriving from social answer three questions.

What is this, why should I care, what do I do next. Asked of five destination pages at phone and
desktop width, signed out on production and signed in against the dev server, because a signed in
account on production is production data and this check does not need one.

Every assertion is about something a person can SEE. A control that exists in the markup but sits
below three screens of editorial is not an answer to "what do I do next", so the first four controls
are also checked for their vertical position.
"""
import json, sys
from playwright.sync_api import sync_playwright

PROD = "https://ruinmytrip.com"
DEV = "http://127.0.0.1:8099"
UAM = ("Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 "
       "(KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1")
UAD = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
       "(KHTML, like Gecko) Chrome/126.0 Safari/537.36")

PROD_CITIES = ["munich-germany", "lisbon-portugal", "chiang-mai-thailand",
               "bangkok-thailand", "amsterdam-netherlands"]
DEV_CITIES = ["munich-germany", "lisbon-portugal"]


def audit(pg, base, slug, width, signed_in):
    errs = []
    pg.on("pageerror", lambda e: errs.append(str(e)))
    r = pg.goto(f"{base}/d/{slug}?utm_source=reddit&utm_medium=post&utm_campaign=coldqa&utm_content=selfcheck",
                wait_until="networkidle")
    body = pg.inner_text("body")

    def top_of(sel):
        el = pg.query_selector(sel)
        if not el:
            return None
        box = el.bounding_box()
        return round(box["y"]) if box else None

    res = {
        "status": r.status if r else None,
        # WHAT IS THIS: a sentence explaining the site to somebody who has never seen it.
        "what_is_it": "RuinMyTrip is where you find the people" in body,
        # WHY CARE: the page has to say what it does for them, in the community block.
        "why_care": ("Travelers who have been, are going, or are asking" in body
                     or "see who else is traveling" in body.lower()),
        # WHAT NEXT: the four first moves.
        "follow": top_of(".cc-follow"),
        "ask": top_of(".cc-ask"),
        "travelers": top_of(".cc-travelers") or top_of(f"a[href*='/d/{slug}/travelers']"),
        "add_trip": top_of(".cc-post-dates") or top_of("a[href*='/trip/new']"),
        "share": top_of(".cc-share, .share-row"),
        "community_block": top_of("#city-community"),
        "overflow": pg.evaluate("document.documentElement.scrollWidth > innerWidth"),
        "viewport_h": pg.evaluate("innerHeight"),
        "js_errors": errs[:2],
        "signed_in": signed_in,
    }
    # Everything that matters has to be reachable without a long scroll: two screens, not ten.
    reach = 2 * res["viewport_h"]
    res["first_moves_within_two_screens"] = all(
        res[k] is not None and res[k] <= reach for k in ("follow", "ask", "travelers", "add_trip"))
    return res


out, fails = {}, []
with sync_playwright() as p:
    b = p.chromium.launch()
    for width, ua in ((390, UAM), (1280, UAD)):
        for slug in PROD_CITIES:
            ctx = b.new_context(viewport={"width": width, "height": 844 if width < 500 else 900},
                                user_agent=ua)
            pg = ctx.new_page()
            out.setdefault(f"prod/{slug}", {})[f"w{width}"] = audit(pg, PROD, slug, width, False)
            ctx.close()

    # Signed in, on the dev server, with a fixture account.
    for width, ua in ((390, UAM), (1280, UAD)):
        ctx = b.new_context(viewport={"width": width, "height": 844 if width < 500 else 900},
                            user_agent=ua)
        pg = ctx.new_page()
        pg.goto(f"{DEV}/login")
        pg.fill("input[name=email]", "fixture_tariq_moreau_1@fixture.invalid")
        pg.fill("input[name=password]", "fixture-not-a-real-password")
        pg.click("button:has-text('Sign in')")
        pg.wait_for_load_state("networkidle")
        signed = "/login" not in pg.url
        for slug in DEV_CITIES:
            out.setdefault(f"dev-signedin/{slug}", {})[f"w{width}"] = audit(pg, DEV, slug, width, signed)
        ctx.close()
    b.close()

for key, widths in out.items():
    for w, r in widths.items():
        where = f"{key} {w}"
        if r["status"] != 200:
            fails.append(f"{where}: status {r['status']}")
        if not r["why_care"]:
            fails.append(f"{where}: nothing says why a traveler should care")
        if r["signed_in"] is False and not r["what_is_it"]:
            fails.append(f"{where}: no line explaining the site to a stranger")
        for k in ("follow", "ask", "travelers", "add_trip"):
            if r[k] is None:
                fails.append(f"{where}: no {k} control at all")
        if not r["first_moves_within_two_screens"]:
            fails.append(f"{where}: first moves below two screens "
                         f"(follow {r['follow']}, ask {r['ask']}, travelers {r['travelers']}, "
                         f"viewport {r['viewport_h']})")
        if r["overflow"]:
            fails.append(f"{where}: horizontal overflow")
        if r["js_errors"]:
            fails.append(f"{where}: js {r['js_errors']}")

print(json.dumps(out, indent=1))
print("FAILURES:", len(fails))
for f in fails:
    print("  ", f)
sys.exit(1 if fails else 0)
