#!/usr/bin/env python3
"""Probe candidate official sources and report what can actually be fact-checked.

Why this exists
---------------
verify_place_sources.py proves a claim still holds. This is the step BEFORE that: deciding which
attractions can be written at all.

The expensive, repetitive part of each batch was discovering, by hand, that (say) the British
Museum and Musee d'Orsay refuse a plain fetch while the Rijksmuseum and Van Gogh Museum serve
everything, and then hunting through a 200KB page for the exact string to assert on. Doing that
manually is what made an attraction cost a quarter of an hour. This script does it in one pass over
as many candidates as you like.

For each URL it reports:
  * whether a plain fetch works at all (a 403 means the attraction cannot be sourced this way)
  * candidate assert strings: money, opening hours and free-admission phrases with surrounding
    context, deduplicated, ready to paste into a facts_checked entry

It shares fetch/normalise/decode with the verifier, so a string that looks matchable here is
matchable there. That is the whole point: no more writing copy against one decoder and checking it
with another.

Usage
-----
    python scripts/probe_place_sources.py URL [URL ...]
    python scripts/probe_place_sources.py --file candidates.txt      # one URL per line, # comments
    python scripts/probe_place_sources.py --file c.txt --json out.json

Exit code is 0 even when sources fail; failures are the finding, not an error.
"""
from __future__ import annotations

import argparse
import importlib.util
import json
import os
import re
import sys
from concurrent.futures import ThreadPoolExecutor

HERE = os.path.dirname(os.path.abspath(__file__))

# Windows defaults stdout to cp1252, which cannot encode a Japanese temple name or a Greek museum
# label. Printing one then raises UnicodeEncodeError and takes the whole run down, losing every
# other result in the batch. Degrade unencodable characters instead of dying: this is a report, and
# a mangled glyph in a context line costs nothing next to a lost run.
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass

# Reuse the verifier's fetch/normalise so probing and verifying agree on encoding and whitespace.
_spec = importlib.util.spec_from_file_location("_rmt_verify", os.path.join(HERE, "verify_place_sources.py"))
_v = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_v)

# What a traveler actually needs pinned down: what it costs, when it opens, whether it is free.
PATTERNS = [
    ("money", r"[€£$¥]\s?\d[\d.,]*|\d[\d.,]*\s?(?:euros?|yen|pounds?)\b"),
    ("free", r"free admission|free entry|admission is free|entry is free|no admission fee|free of charge"),
    ("hours", r"\b\d{1,2}[:.]\d{2}\s?(?:a\.?m\.?|p\.?m\.?)?\s?(?:to|-|–|until)\s?\d{1,2}[:.]\d{2}"),
    ("closed", r"closed on \w+|open all year|daily from \d"),
]

CONTEXT_BEFORE = 75
CONTEXT_AFTER = 45
MAX_PER_KIND = 4


def candidates(text: str) -> dict[str, list[str]]:
    out: dict[str, list[str]] = {}
    for kind, pat in PATTERNS:
        found, seen = [], set()
        for m in re.finditer(pat, text, re.I):
            start = max(0, m.start() - CONTEXT_BEFORE)
            frag = text[start:m.end() + CONTEXT_AFTER].strip()
            # Dedupe on the neighbourhood, not the match: a price table repeats the same number
            # many times and only the first occurrence is useful as an assertion.
            key = frag[:40]
            if key in seen:
                continue
            seen.add(key)
            found.append(frag)
            if len(found) >= MAX_PER_KIND:
                break
        if found:
            out[kind] = found
    return out


def probe(url: str) -> dict:
    ok, body = _v.fetch(url)
    if not ok:
        return {"url": url, "ok": False, "detail": body, "candidates": {}}
    text = _v.normalise(body)
    return {"url": url, "ok": True, "detail": f"{len(body) // 1024}KB",
            "candidates": candidates(text)}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("urls", nargs="*")
    ap.add_argument("--file", help="file with one URL per line; blank lines and # comments ignored")
    ap.add_argument("--json", help="write the full report here")
    ap.add_argument("--workers", type=int, default=8)
    a = ap.parse_args()

    urls = list(a.urls)
    if a.file:
        with open(a.file, encoding="utf-8") as fh:
            for line in fh:
                line = line.strip()
                if line and not line.startswith("#"):
                    urls.append(line)
    urls = list(dict.fromkeys(urls))  # de-dupe, keep order
    if not urls:
        print("no URLs given", file=sys.stderr)
        return 1

    with ThreadPoolExecutor(max_workers=min(a.workers, len(urls))) as pool:
        reports = list(pool.map(probe, urls))

    usable = 0
    for r in reports:
        if not r["ok"]:
            print(f"\nUNUSABLE  {r['url']}\n          {r['detail']}")
            continue
        if not r["candidates"]:
            print(f"\nFETCHES, NOTHING TO ASSERT  {r['url']}  ({r['detail']})")
            continue
        usable += 1
        print(f"\nOK  {r['url']}  ({r['detail']})")
        for kind, frags in r["candidates"].items():
            for f in frags:
                print(f"    [{kind}] ...{f}...")

    print(f"\n{usable} of {len(reports)} sources are usable for fact-checked copy.")
    if a.json:
        with open(a.json, "w", encoding="utf-8") as fh:
            json.dump({"sources": reports}, fh, indent=2, ensure_ascii=False)
        print(f"report written to {a.json}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
