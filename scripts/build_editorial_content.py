#!/usr/bin/env python3
"""Normalise destination research files into database/editorial/content.json.

The research step and the publish step are deliberately separate. This script is the seam: it
maps whatever shape the research produced onto the exact field names publish_editorial.php
validates, and it enforces the house style rules (no em dashes, no markdown headings, no claimed
visit) BEFORE anything reaches a database, so a style slip fails here rather than on a live page.

It is not a content generator. It never writes a fact that was not in the research file.

  python scripts/build_editorial_content.py <research_dir> [--out database/editorial/content.json]
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import re
import sys

# Research files identify destinations by name; the site identifies them by an existing slug.
# Mapping is explicit so a renamed or unexpected destination fails loudly instead of being
# silently slugified into a row that does not exist.
NAME_TO_SLUG = {
    "kyoto": "kyoto-japan",
    "lisbon": "lisbon-portugal",
    "banff": "banff-canada",
    "reykjavik": "reykjavik-iceland",
    "reykjavík": "reykjavik-iceland",
    "hoi an": "hoi-an-vietnam",
    "hội an": "hoi-an-vietnam",
    "oaxaca": "oaxaca-mexico",
    "oaxaca de juarez": "oaxaca-mexico",
    "oaxaca de juárez": "oaxaca-mexico",
    "marrakech": "marrakech-morocco",
    "marrakesh": "marrakech-morocco",
    "queenstown": "queenstown-nz",
}

# Display names on the site, so "Oaxaca de Juarez" does not become the review subject when the
# destination row is called "Oaxaca".
SLUG_TO_NAME = {
    "kyoto-japan": "Kyoto",
    "lisbon-portugal": "Lisbon",
    "banff-canada": "Banff",
    "reykjavik-iceland": "Reykjavik",
    "hoi-an-vietnam": "Hoi An",
    "oaxaca-mexico": "Oaxaca",
    "marrakech-morocco": "Marrakech",
    "queenstown-nz": "Queenstown",
}


def clean(text: str) -> str:
    """House style: no em/en dashes, no markdown headings or bullets, no smart-quote noise."""
    if not isinstance(text, str):
        return text
    t = text.replace("—", ",").replace("–", "-")
    t = t.replace("‘", "'").replace("’", "'")
    t = t.replace("“", '"').replace("”", '"')
    lines = []
    for line in t.split("\n"):
        line = re.sub(r"^\s{0,3}#{1,6}\s*", "", line)      # markdown headings
        line = re.sub(r"^\s*[-*•]\s+", "", line)      # bullets
        line = re.sub(r"\*\*(.+?)\*\*", r"\1", line)       # bold
        lines.append(line.rstrip())
    t = "\n".join(lines)
    t = re.sub(r"\n{3,}", "\n\n", t)
    return t.strip()


def slugify(s: str) -> str:
    return re.sub(r"-+", "-", re.sub(r"[^a-z0-9]+", "-", s.lower())).strip("-") or "item"


# Commons only serves a fixed set of derivative widths and 400s anything else; 1280 is on the
# allowed list and is plenty for a hero image.
WIKIMEDIA_THUMB_PX = 1280


def wikimedia_thumb(url: str, px: int = WIKIMEDIA_THUMB_PX) -> str:
    """Rewrite a Commons original-file URL to a scaled thumbnail.

    The originals are the full camera files, routinely 10-20MB. Shipping one of those as a hero
    image is a broken page on any phone. Commons serves resized derivatives from a parallel
    /thumb/ path, so the credited source stays identical while the bytes become sane.
    """
    m = re.match(r"^(https://upload\.wikimedia\.org/wikipedia/commons)/(\w)/(\w{2})/(.+\.(?:jpg|jpeg|png))$",
                 url, re.IGNORECASE)
    if not m:
        return url
    base, a, b, fname = m.groups()
    return f"{base}/thumb/{a}/{b}/{fname}/{px}px-{fname}"


def photo_fields(d: dict) -> dict:
    """Accept either a nested `photo` object or flat photo_* keys."""
    p = d.get("photo") if isinstance(d.get("photo"), dict) else d
    return {
        "photo_url": wikimedia_thumb(p.get("photo_url") or p.get("url") or ""),
        "photo_original_url": p.get("photo_url") or p.get("url") or "",
        "photo_credit": p.get("photo_credit") or p.get("credit") or "",
        "photo_license": p.get("photo_license") or p.get("license") or "",
        "photo_source_url": p.get("photo_source_page") or p.get("photo_source_url") or p.get("source_page") or "",
    }


def convert(d: dict, source_file: str) -> dict:
    raw_name = (d.get("name") or "").strip()
    slug = NAME_TO_SLUG.get(raw_name.lower())
    if not slug:
        raise SystemExit(f"{source_file}: no slug mapping for destination name {raw_name!r}")

    tips = [clean(t) for t in (d.get("tips") or []) if isinstance(t, str) and t.strip()]
    guide = d.get("guide") or {}

    out = {
        "slug": slug,
        "name": SLUG_TO_NAME[slug],
        "country": clean(d.get("country", "")),
        "summary": clean(d.get("summary", "")),
        "headline": clean(d.get("headline", "")),
        "body": clean(d.get("body", "")),
        "what_great": clean(d.get("what_great", "")),
        "what_ruined": clean(d.get("what_ruined", "")),
        "rating": int(d.get("editorial_rating") or 0),
        "safety_rating": int(d.get("safety_rating") or 0),
        "value_rating": int(d.get("value_rating") or 0),
        "tips": tips,
        "guide": {
            # Stable, short, keyword-bearing. Derived from the destination rather than the
            # headline so that editing a title later does not orphan the guide's URL.
            "slug": f"{slug}-travel-guide",
            "title": clean(guide.get("title", "")),
            "summary": clean(guide.get("summary", "")),
            "body": clean(guide.get("body", "")),
        },
        # Kept for the audit trail; publish_editorial.php ignores unknown keys.
        "rating_rationale": {
            "overall": clean(d.get("editorial_rating_justification", "")),
            "safety": clean(d.get("safety_rating_justification", "")),
            "value": clean(d.get("value_rating_justification", "")),
        },
        "facts_checked": d.get("facts_checked", []),
        "source_file": source_file,
    }
    out.update(photo_fields(d))
    return out


def out_name(slug: str) -> str:
    return SLUG_TO_NAME.get(slug, slug)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("research_dir")
    ap.add_argument("--out", default="database/editorial/content.json")
    a = ap.parse_args()

    files = sorted(glob.glob(os.path.join(a.research_dir, "rmt_research_*.json")))
    if not files:
        print(f"no research files in {a.research_dir}", file=sys.stderr)
        return 1

    items, seen = [], set()
    for f in files:
        with open(f, encoding="utf-8") as fh:
            data = json.load(fh)
        for d in data.get("destinations", []):
            item = convert(d, os.path.basename(f))
            if item["slug"] in seen:
                print(f"duplicate destination {item['slug']}", file=sys.stderr)
                return 1
            seen.add(item["slug"])
            items.append(item)

    items.sort(key=lambda x: x["slug"])
    os.makedirs(os.path.dirname(a.out), exist_ok=True)
    with open(a.out, "w", encoding="utf-8") as fh:
        json.dump({"destinations": items}, fh, indent=2, ensure_ascii=False)
        fh.write("\n")

    print(f"wrote {a.out} with {len(items)} destinations")
    for i in items:
        print(f"  {i['slug']:<20} rating={i['rating']} tips={len(i['tips'])} "
              f"body={len(i['body'])} guide={len(i['guide']['body'])}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
