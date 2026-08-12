#!/usr/bin/env python3
"""Re-check every sourced claim in database/editorial/places.json against its cited page.

Why this exists
---------------
The slow part of publishing editorial place content is not writing it, it is proving the numbers.
Doing that by hand costs a quarter of an hour per attraction and, worse, it decays silently: a
museum raises its admission in the spring and the page keeps quoting last year's price with the
word "verified" next to it.

So every entry in `facts_checked` carries an `assert_text`: the exact string that must still appear
on the cited page. This script fetches each source once and looks for it. That turns fact-checking
from a manual reading task into a re-runnable check, and it is the same check whether there are two
attractions or two hundred.

What a PASS means precisely: the cited page still contains the asserted string. It does NOT mean a
human agreed the fact follows from the page. That judgement happens once, when the entry is
written; this script defends it from drift afterwards.

Matching is deliberately forgiving about presentation and strict about content: HTML tags are
stripped, entities unescaped, whitespace collapsed, non-breaking spaces normalised, and comparison
is case-insensitive. It will not paper over a changed number.

Usage
-----
    python scripts/verify_place_sources.py                      # check everything
    python scripts/verify_place_sources.py --slug rome-italy    # one destination
    python scripts/verify_place_sources.py --json report.json   # machine-readable output

Exit code is 1 if any assertion fails or any source is unreachable, so it can gate a publish.
"""
from __future__ import annotations

import argparse
import html
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLACES_JSON = os.path.join(HERE, "database", "editorial", "places.json")

UA = "RuinMyTrip/1.0 (+https://ruinmytrip.com; editorial fact verification)"
TIMEOUT = 30

_SCRIPT_STYLE = re.compile(r"<(script|style)\b.*?</\1>", re.I | re.S)
_TAG = re.compile(r"<[^>]+>")
_WS = re.compile(r"\s+")


def normalise(text: str) -> str:
    """Strip markup and flatten presentation so a match survives reformatting but not a rewrite."""
    text = _SCRIPT_STYLE.sub(" ", text)
    text = _TAG.sub(" ", text)
    text = html.unescape(text)
    # Prices and hours are riddled with non-breaking and thin spaces; treat them as ordinary ones.
    text = text.replace(" ", " ").replace(" ", " ").replace(" ", " ")
    return _WS.sub(" ", text).strip().lower()


_META_CHARSET = re.compile(rb"""charset=["']?\s*([a-zA-Z0-9_\-]+)""", re.I)


def decode(raw: bytes, header_charset: str | None) -> str:
    """Decode a page, getting the currency symbols right.

    This matters more than it looks. Half these sites serve windows-1252, where the euro sign is
    byte 0x80. Decoding that as latin-1 (the obvious fallback) silently turns EUR into a control
    character, so an assert_text containing a price could never match and every price assertion
    would fail for a reason that has nothing to do with the price being wrong. Order: the charset
    the server declares, then the one the document declares, then utf-8, then cp1252 (a superset of
    latin-1 for the printable range, so it is the safer of the two to guess).
    """
    candidates = [c for c in (header_charset, None) if c]
    m = _META_CHARSET.search(raw[:4096])
    if m:
        try:
            candidates.append(m.group(1).decode("ascii"))
        except UnicodeDecodeError:
            pass
    candidates += ["utf-8", "cp1252"]

    seen = set()
    for enc in candidates:
        enc = enc.lower().strip()
        if enc in seen:
            continue
        seen.add(enc)
        try:
            return raw.decode(enc)
        except (UnicodeDecodeError, LookupError):
            continue
    return raw.decode("utf-8", "replace")


# Retried once with a pause. Several of these sites serve a page happily and then 403 the next
# request from the same address seconds later; that is rate limiting, not a missing page, and
# without a retry the verifier fails a publish for a reason that has nothing to do with the facts.
# Kept deliberately small: this is a courtesy retry, not an attempt to defeat a block.
RETRIES = 2
RETRY_PAUSE = 4.0


def fetch(url: str) -> tuple[bool, str]:
    req = urllib.request.Request(
        url,
        headers={"User-Agent": UA, "Accept": "text/html,*/*", "Accept-Language": "en"},
    )
    last = "not attempted"
    for attempt in range(RETRIES):
        if attempt:
            time.sleep(RETRY_PAUSE)
        try:
            with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
                raw = r.read()
                charset = r.headers.get_content_charset()
            return True, decode(raw, charset)
        except urllib.error.HTTPError as e:
            last = f"HTTP {e.code}"
            # 404 and 410 are settled answers; retrying them just wastes time.
            if e.code in (404, 410):
                break
        except Exception as e:  # network, TLS, DNS, timeout
            last = f"{type(e).__name__}: {e}"
    return False, last


def check_place(place: dict) -> dict:
    name = place.get("name", "?")
    dest = place.get("destination_slug", "?")
    facts = place.get("facts_checked", []) or []
    results = []

    # One fetch per distinct URL, not per fact: several facts usually cite the same official page.
    urls = sorted({f.get("url", "") for f in facts if f.get("url")})
    pages: dict[str, tuple[bool, str]] = {}
    if urls:
        with ThreadPoolExecutor(max_workers=min(6, len(urls))) as pool:
            for u, res in zip(urls, pool.map(fetch, urls)):
                pages[u] = res

    for f in facts:
        url = f.get("url", "")
        assert_text = f.get("assert_text", "")
        entry = {"fact": f.get("fact", ""), "url": url, "assert_text": assert_text}
        if not url or not assert_text:
            entry["status"] = "INVALID"
            entry["detail"] = "missing url or assert_text"
        else:
            ok, body = pages.get(url, (False, "not fetched"))
            if not ok:
                entry["status"] = "UNREACHABLE"
                entry["detail"] = body
            elif normalise(assert_text) in normalise(body):
                entry["status"] = "PASS"
                entry["detail"] = ""
            else:
                entry["status"] = "FAIL"
                entry["detail"] = "assert_text not present on the page"
        results.append(entry)

    return {"destination_slug": dest, "name": name, "facts": results}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", default=PLACES_JSON)
    ap.add_argument("--slug", help="only check this destination_slug")
    ap.add_argument("--name", help="only check places whose name contains this (case-insensitive)")
    ap.add_argument("--json", help="write a machine-readable report here")
    a = ap.parse_args()

    with open(a.file, encoding="utf-8") as fh:
        places = json.load(fh).get("places", [])

    if a.slug:
        places = [p for p in places if p.get("destination_slug") == a.slug]
    if a.name:
        places = [p for p in places if a.name.lower() in p.get("name", "").lower()]
    if not places:
        print("no places matched", file=sys.stderr)
        return 1

    reports = [check_place(p) for p in places]

    counts = {"PASS": 0, "FAIL": 0, "UNREACHABLE": 0, "INVALID": 0}
    for rep in reports:
        print(f"\n{rep['destination_slug']} / {rep['name']}")
        for f in rep["facts"]:
            counts[f["status"]] = counts.get(f["status"], 0) + 1
            mark = "ok  " if f["status"] == "PASS" else f["status"]
            print(f"  [{mark}] {f['assert_text'][:70]}")
            if f["status"] != "PASS":
                print(f"         {f['url']}")
                print(f"         {f['detail']}")

    total = sum(counts.values())
    print(f"\n{total} assertions across {len(reports)} places: "
          + ", ".join(f"{k}={v}" for k, v in counts.items() if v))

    if a.json:
        with open(a.json, "w", encoding="utf-8") as fh:
            json.dump({"places": reports, "counts": counts}, fh, indent=2, ensure_ascii=False)
        print(f"report written to {a.json}")

    bad = counts["FAIL"] + counts["UNREACHABLE"] + counts["INVALID"]
    if bad:
        print(f"\n{bad} assertion(s) did not pass. Fix the entry or correct the copy before publishing.")
    return 1 if bad else 0


if __name__ == "__main__":
    raise SystemExit(main())
