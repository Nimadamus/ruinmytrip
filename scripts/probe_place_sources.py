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

# Currencies that are not the euro, the pound, the dollar or the yen.
#
# The original money pattern knew four symbols, which meant every attraction priced in zloty,
# koruna, forint, krona, krone or franc came back "FETCHES, NOTHING TO ASSERT" and was written off
# as unsourceable. It was not: the price was on the page in a currency the regex could not see.
# That silently capped coverage at western Europe. Trailing-symbol currencies (350 kr, 45 zl) are as
# common as leading ones, so both orders are matched.
_CURRENCY = (r"kr|kr\.|dkk|sek|nok|isk|z[lł]|pln|k[cč]|czk|ft|huf|chf|sgd|aud|nzd|zar|krw|₩|₹|inr"
             r"|thb|฿|eur|gbp|usd|jpy|ils|₪|try|₺|ron|lei|bgn|hrk|rsd|uah|₴|mxn|brl|r\$|ars|clp|cop"
             r"|pen|s/|egp|mad|dh|aed|sar|qar|myr|rm|idr|rp|php|₱|vnd|₫|twd|hkd|cny|rmb|元|円|원")
PATTERNS = [
    ("money",
     # Symbol first, optionally spaced: "€20", "€ 20,50", "£ 12.00".
     r"[€£$¥]\s?\d[\d.,]*"
     # Amount then symbol, which most of continental Europe writes: "20,50 €", "12.00 £".
     r"|\d[\d.,]*\s?[€£$¥]"
     r"|\d[\d.,]*\s?(?:euros?|yen|pounds?|dollars?|francs?|krone[rn]?|kronor|zlotych|forint)\b"
     rf"|\d[\d.,]*\s?(?:{_CURRENCY})\b"
     rf"|\b(?:{_CURRENCY})\s?\d[\d.,]*"),
    ("free", r"free admission|free entry|admission is free|entry is free|no admission fee|free of charge"),
    ("hours", r"\b\d{1,2}[:.]\d{2}\s?(?:a\.?m\.?|p\.?m\.?)?\s?(?:to|-|–|until)\s?\d{1,2}[:.]\d{2}"),
    ("closed", r"closed on \w+|open all year|daily from \d"),
]

# Access facts are a different search from price facts, and they matter more.
#
# A wrong price costs a traveler a few euros of surprise. A wrong access claim sends a wheelchair
# user across a city to a building they cannot get into, so these sections were left empty rather
# than written from memory. Filling them needs the same assert-a-string discipline the prices get,
# which needs a pattern that can see access language rather than currency.
ACCESS_PATTERNS = [
    ("step-free", r"step[- ]free|barrier[- ]free|wheelchair accessible|fully accessible"
                  r"|accessible entrance|level access|barrierefrei|sans obstacle"),
    ("lift", r"\blifts?\b|\belevators?\b|\bascenseur|\baufzug|\bramp(?:s|ed)?\b"),
    ("wheelchair", r"wheelchairs? (?:are |can be |available|loan|hire|provided|on request)"
                   r"|manual wheelchairs?|fauteuil roulant|rollstuhl"),
    ("not-accessible", r"not (?:wheelchair )?accessible|no lift|no elevator|stairs only"
                       r"|cannot be accessed|unable to access|steps only"),
    ("companion", r"(?:carer|companion|assistant|helper|escort)s? (?:go |are |enter |admitted )?free"
                  r"|free (?:of charge )?for (?:a )?(?:carer|companion|assistant)"),
]

CONTEXT_BEFORE = 75
CONTEXT_AFTER = 45
MAX_PER_KIND = 4


def candidates(text: str, patterns=None) -> dict[str, list[str]]:
    out: dict[str, list[str]] = {}
    for kind, pat in (patterns or PATTERNS):
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


# Words that mark a visitor-information page, in the languages these sites are actually written in.
# Guessing /en/visit is what produced 19 straight 404s in one batch: official sites publish prices
# at /en/plan-your-visit, /besuch/preise, /bezoek/tickets, /zwiedzanie, and a dozen other shapes. The
# site's own navigation already knows the answer, so ask it instead of guessing.
NAV_WORDS = (
    "visit visitor visiting plan-your-visit ticket tickets admission entry entrance price prices "
    "opening-hours hours fees "
    "besuch besuchen eintritt preise oeffnungszeiten öffnungszeiten "
    "visita visitar entrada entradas horario horarios tarifa precios "
    "visite billets tarifs horaires "
    "biglietti orari prezzi ingresso "
    "bezoek toegang openingstijden kaartjes "
    "bilety zwiedzanie ceny godziny "
    "priser oppettider öppettider besok besök "
    "latogatas jegyarak vstupne navstevnici "
    "kanransuru riyou"
).split()

# Pages that match a nav word but never carry a price: shops, memberships, donations, school groups.
NAV_NOISE = ("shop", "membership", "member", "donate", "support-us", "school", "education", "press",
             "job", "career", "privacy", "cookie", "newsletter", "gift", "wedding", "venue-hire",
             "corporate", "accessib")

# Restaurants and hotels do not talk about "tickets" or "admission", and hunting for those words
# found 4 usable pages across 20 official venue sites. They publish under menu, carte, speisekarte,
# reservations, rooms, rates. Same failure class as guessing /en/visit: the vocabulary, not the
# site, was the limit.
DINE_NAV_WORDS = (
    "menu menus carte lacarte food drinks wine lunch dinner brunch breakfast reservation "
    "reservations booking book book-a-table opening hours prices restaurant bar cafe "
    "speisekarte getraenke getränke reservierung oeffnungszeiten öffnungszeiten preise "
    "cartes reserver réserver horaires tarifs "
    "carta reservas horarios precios comer "
    "menu prenota prenotazioni orari prezzi "
    "ementa reservas horarios "
    "jidelni-listek rezervace "
    "etlap asztalfoglalas"
).split()

STAY_NAV_WORDS = (
    "rooms room suites accommodation stay rates prices booking book reservation reserve "
    "offers packages facilities amenities hotel guest-rooms "
    "zimmer suiten uebernachten übernachten preise buchen reservierung ausstattung "
    "chambres suites tarifs reserver réserver sejour séjour "
    "habitaciones alojamiento tarifas reservar "
    "camere alloggio tariffe prenota "
    "quartos alojamento reservas"
).split()

# Menus and rate pages are the target here, so "shop" and "gift" still are not, but "restaurant"
# and "bar" very much are.
HOSPITALITY_NAV_NOISE = ("job", "career", "privacy", "cookie", "newsletter", "press", "recruit",
                         "vacancies", "franchise", "supplier", "gift-card", "giftcard", "e-shop",
                         "webshop", "online-shop")

# The access hunt is the inverse: what is noise for prices is the target here, and vice versa.
ACCESS_NAV_WORDS = (
    "accessibility accessible access disabled-access wheelchair disability mobility step-free "
    "barrier-free visually-impaired hearing "
    "barrierefrei barrierefreiheit zugaenglichkeit rollstuhl "
    "accessibilite accessibilité handicap mobilite mobilité "
    "accesibilidad accesible discapacidad movilidad "
    "accessibilita accessibilità disabili "
    "toegankelijkheid rolstoel "
    "tillganglighet tillgänglighet"
).split()

ACCESS_NAV_NOISE = ("shop", "donate", "job", "career", "privacy", "cookie", "newsletter",
                    "press", "wedding", "venue-hire", "corporate", "web-accessibility",
                    "accessibility-statement", "digital-accessibility")

_HREF = re.compile(r"<a\b[^>]*href=[\"']([^\"'#]+)[\"'][^>]*>(.*?)</a>", re.I | re.S)


_SITEMAP_LOC = re.compile(r"<loc>\s*([^<\s]+)\s*</loc>", re.I)

# What the run is hunting for. Prices and access facts live on different pages, described by
# opposite vocabularies: an accessibility page is noise when you want a ticket price, and a ticket
# page rarely says whether there is a lift.
MODES = {
    "visit": (NAV_WORDS, NAV_NOISE, PATTERNS),
    "access": (ACCESS_NAV_WORDS, ACCESS_NAV_NOISE, ACCESS_PATTERNS),
    "dine": (DINE_NAV_WORDS, HOSPITALITY_NAV_NOISE, PATTERNS),
    "stay": (STAY_NAV_WORDS, HOSPITALITY_NAV_NOISE, PATTERNS),
}
_mode = "visit"


def mode_words():
    return MODES[_mode][0]


def mode_noise():
    return MODES[_mode][1]


def mode_patterns():
    return MODES[_mode][2]


def discover_via_sitemap(root: str, limit: int) -> list[str]:
    """Fall back to the site's own sitemap when its navigation is unreadable.

    A JavaScript-rendered menu produces no <a href> for a plain fetch, so nav discovery finds
    nothing and the site looks like it publishes no visitor information. Its sitemap is static XML
    and lists the same pages. This is still the site telling us where its pages are, not guesswork.
    """
    import urllib.parse

    split = urllib.parse.urlsplit(root)
    base = f"{split.scheme}://{split.netloc}"
    urls: list[tuple[int, str]] = []
    for path in ("/sitemap.xml", "/sitemap_index.xml", "/sitemap-index.xml"):
        ok, body = _v.fetch(base + path)
        if not ok:
            continue
        for loc in _SITEMAP_LOC.findall(body)[:2000]:
            low = loc.lower()
            if any(n in low for n in mode_noise()):
                continue
            score = sum(1 for w in mode_words() if w in low)
            if score:
                urls.append((score + (2 if "/en" in low else 0), loc))
        if urls:
            break
    urls.sort(key=lambda kv: (-kv[0], len(kv[1])))
    return [u for _, u in urls[:limit]]


def discover(root: str, limit: int = 6) -> list[str]:
    """Return the most likely visitor-information URLs linked from a site's own navigation."""
    import urllib.parse

    ok, body = _v.fetch(root)
    if not ok:
        # A root that will not load is not proof the site is unusable: plenty of them redirect a
        # bare domain oddly while their sitemap is served fine.
        return discover_via_sitemap(root, limit)
    root_host = urllib.parse.urlsplit(root).netloc.lower().lstrip("www.")

    scored: dict[str, int] = {}
    for href, anchor in _HREF.findall(body):
        url = urllib.parse.urljoin(root, href.strip())
        split = urllib.parse.urlsplit(url)
        if split.scheme not in ("http", "https"):
            continue
        # Stay on the institution's own site. An off-site link is a reseller or a social account,
        # and citing a reseller for a price is the one thing this whole pipeline exists to avoid.
        if not split.netloc.lower().lstrip("www.").endswith(root_host):
            continue
        url = urllib.parse.urlunsplit(split._replace(fragment=""))
        haystack = f"{split.path.lower()} {_v.normalise(anchor).lower()}"
        if any(n in haystack for n in mode_noise()):
            continue
        score = sum(1 for w in mode_words() if w in haystack)
        if not score:
            continue
        # Prefer an English page: it is the one we can assert a readable string from.
        if "/en" in split.path.lower():
            score += 2
        scored[url] = max(scored.get(url, 0), score)

    found = [u for u, _ in sorted(scored.items(), key=lambda kv: (-kv[1], len(kv[0])))[:limit]]
    return found or discover_via_sitemap(root, limit)


def probe(url: str) -> dict:
    ok, body = _v.fetch(url)
    if not ok:
        return {"url": url, "ok": False, "detail": body, "candidates": {}}
    text = _v.normalise(body)
    return {"url": url, "ok": True, "detail": f"{len(body) // 1024}KB",
            "candidates": candidates(text, mode_patterns())}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("urls", nargs="*")
    ap.add_argument("--file", help="file with one URL per line; blank lines and # comments ignored")
    ap.add_argument("--json", help="write the full report here")
    ap.add_argument("--workers", type=int, default=8)
    ap.add_argument("--discover", action="store_true",
                    help="treat each URL as a site root: follow its own navigation to the visitor "
                         "information pages, then probe those")
    ap.add_argument("--from-places", action="store_true",
                    help="probe the URLs the places file ALREADY cites, instead of discovering new "
                         "ones. Nav discovery only finds facts that live on their own page, and "
                         "plenty do not: d'Angleterre's wheelchair access is an FAQ accordion on "
                         "the rooms page we were already citing. Cheap, because those pages are "
                         "usually cached already.")
    ap.add_argument("--places-file",
                    default=os.path.join(os.path.dirname(HERE), "database", "editorial", "places.json"),
                    help="places file for --from-places")
    ap.add_argument("--slug", help="with --from-places, limit to one destination_slug")
    ap.add_argument("--mode", choices=sorted(MODES), default="visit",
                    help="what to hunt for. 'visit' finds ticket prices and opening hours; "
                         "'access' finds step-free routes, lifts and wheelchair provision, which "
                         "live on different pages described by an opposite vocabulary.")
    ap.add_argument("--discover-limit", type=int, default=6,
                    help="max pages to follow per site root (default 6)")
    a = ap.parse_args()

    global _mode
    _mode = a.mode

    urls = list(a.urls)
    if a.from_places:
        with open(a.places_file, encoding="utf-8") as fh:
            for place in json.load(fh).get("places", []):
                if a.slug and place.get("destination_slug") != a.slug:
                    continue
                for fact in place.get("facts_checked") or []:
                    if fact.get("url"):
                        urls.append(fact["url"])
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

    if a.discover:
        roots = urls
        with ThreadPoolExecutor(max_workers=min(a.workers, len(roots))) as pool:
            found = list(pool.map(lambda r: (r, discover(r, a.discover_limit)), roots))
        urls = []
        for root, links in found:
            if links:
                print(f"{root}\n    " + "\n    ".join(links))
            else:
                print(f"{root}\n    (no visitor-information links found)")
            urls.extend(links)
        urls = list(dict.fromkeys(urls))
        if not urls:
            print("\nnothing discovered to probe", file=sys.stderr)
            return 1
        print(f"\ndiscovered {len(urls)} pages from {len(roots)} site roots; probing them\n")

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
