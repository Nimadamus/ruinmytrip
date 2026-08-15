#!/usr/bin/env python3
"""Report what the editorial place layer actually covers, and what it does not.

Why this exists
---------------
"54 attractions" is not a status. It does not say how many are fully written, how many have a price
a script can still confirm, or which candidates were dropped and why. Those are the numbers that
decide what the next batch should be, and reconstructing them by hand from places.json and a
verifier log is exactly the sort of thing that stops being done.

Reads database/editorial/places.json and, when present, the JSON report written by
verify_place_sources.py --json, so the verification column reflects a real run rather than a claim.

    python scripts/verify_place_sources.py --json .work/verify.json
    python scripts/coverage_report.py --verify .work/verify.json
"""
from __future__ import annotations

import argparse
import collections
import json
import os
import re
import sys

for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLACES_JSON = os.path.join(HERE, "database", "editorial", "places.json")

# The thirteen optional section columns. A page with all of them is complete; the publish validator
# only insists on nine, so "published" and "complete" are different questions.
SECTIONS = ("what_it_is", "why_go", "the_good", "the_downsides", "best_for", "skip_if",
            "practical", "tickets", "getting_there", "location_context", "time_needed",
            "accessibility", "verdict")

# A fact that pins down what it costs, as opposed to opening hours or access rules.
#
# Word matching alone is not enough, and getting that wrong here is the same mistake the probe's
# money pattern made: an assertion reading "adult £22.00 £26.00" contains no price *word* and was
# reported as having no verified price, on a page whose price is verified three times over. So a
# currency amount counts as a price on its own.
PRICE_WORDS = ("price", "admission", "ticket", "costs", "euro", "free", "fee", "charge")
PRICE_AMOUNT = re.compile(
    r"[€£$¥₩₪₺₹]\s?\d"
    r"|\d[\d.,]*\s?[€£$¥]"
    r"|\d[\d.,]*\s?(?:eur|gbp|usd|chf|sek|dkk|nok|isk|pln|z[lł]|czk|k[cč]|huf|ft|yen|円|kr)\b",
    re.I,
)


def is_price_fact(fact: dict) -> bool:
    text = f"{fact.get('fact', '')} {fact.get('assert_text', '')}".lower()
    return any(w in text for w in PRICE_WORDS) or bool(PRICE_AMOUNT.search(text))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", default=PLACES_JSON)
    ap.add_argument("--verify", help="JSON report from verify_place_sources.py --json")
    a = ap.parse_args()

    with open(a.file, encoding="utf-8") as fh:
        places = json.load(fh).get("places", [])

    status: dict[tuple[str, str], dict[str, int]] = {}
    if a.verify:
        with open(a.verify, encoding="utf-8") as fh:
            for rep in json.load(fh).get("places", []):
                key = (rep["destination_slug"], rep["name"])
                counts: dict[str, int] = collections.Counter()
                for f in rep["facts"]:
                    counts[f["status"]] += 1
                    if is_price_fact(f) and f["status"] == "PASS":
                        counts["PRICE_PASS"] += 1
                status[key] = counts

    by_dest = collections.Counter(p["destination_slug"] for p in places)
    complete = [p for p in places if all(p.get(s) for s in SECTIONS)]
    priced, unverified, problems = [], [], []

    for p in places:
        key = (p["destination_slug"], p["name"])
        counts = status.get(key)
        if counts is None:
            continue
        if counts.get("PRICE_PASS"):
            priced.append(key)
        if counts.get("UNCHECKED"):
            unverified.append((key, "source blocked, accepted"))
        bad = counts.get("FAIL", 0) + counts.get("UNREACHABLE", 0) + counts.get("INVALID", 0) + counts.get("BLOCKED", 0)
        if bad:
            problems.append((key, ", ".join(f"{k}={v}" for k, v in sorted(counts.items())
                                            if k not in ("PASS", "PRICE_PASS"))))

    print(f"attractions:            {len(places)}")
    print(f"destinations covered:   {len(by_dest)}")
    print(f"complete editorial:     {len(complete)} of {len(places)} have all {len(SECTIONS)} sections")
    if status:
        print(f"verified pricing:       {len(priced)} of {len(places)} have a price assertion passing now")
        print(f"accepted-blocked:       {len(unverified)}")
        print(f"pipeline problems:      {len(problems)}")
    else:
        print("verified pricing:       (no --verify report given)")

    print("\nper destination:")
    for slug, n in sorted(by_dest.items(), key=lambda kv: (-kv[1], kv[0])):
        print(f"  {n:>2}  {slug}")

    incomplete = [p for p in places if not all(p.get(s) for s in SECTIONS)]
    if incomplete:
        print("\nnot fully written (missing optional sections):")
        for p in incomplete:
            missing = [s for s in SECTIONS if not p.get(s)]
            print(f"  {p['destination_slug']}/{p['name']}: missing {', '.join(missing)}")

    if problems:
        print("\nassertions not passing:")
        for (slug, name), detail in problems:
            print(f"  {slug}/{name}: {detail}")

    if unverified:
        print("\ncited sources accepted as blocked (see database/blocked_sources.json):")
        for (slug, name), why in unverified:
            print(f"  {slug}/{name}: {why}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
