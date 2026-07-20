#!/usr/bin/env python3
"""Apply adversarial fact-check corrections to database/editorial/content.json.

Every replacement below came out of a fact-check pass that tried to REFUTE the copy, and each was
re-verified against the primary source before being written here. Two kinds of change:

  WRONG      - the text asserted something a primary source contradicts. Corrected to the source.
  UNSOURCED  - the text asserted a number no primary source could be found for. Rewritten
               qualitatively. On a site whose entire pitch is honesty, an unsourceable number is
               worse than no number.

Fails loudly if any target string is missing, so a silent partial patch is impossible.

  python scripts/patch_editorial_factcheck.py [--dry-run]
"""
from __future__ import annotations

import json
import sys

PATH = "database/editorial/content.json"

# (slug, field, old, new, reason)
# field is "body", "what_ruined", "guide", or "tips"
PATCHES = [
    # --- WRONG: INAH lists Mitla at 210 pesos general; 105 is nationals/residents only.
    # Verified: https://www.inah.gob.mx/zonas/zona-arqueologica-de-mitla
    # "Costo: $210.00 pesos  Nacionales y extranjeros con residencia en Mexico: $105.00 pesos"
    ("oaxaca-mexico", "body",
     "is listed by INAH at 105 pesos with hours of 8:00 to 17:00",
     "is listed by INAH at 210 pesos general admission, 105 pesos for Mexican nationals and "
     "residents, with hours of 8:00 to 17:00",
     "WRONG: quoted the residents-only price as the general price"),
    ("oaxaca-mexico", "guide",
     "is listed by INAH at 105 pesos, 8:00 to 17:00, last access 16:30",
     "is listed by INAH at 210 pesos general and 105 pesos for Mexican nationals and residents, "
     "8:00 to 17:00, last access 16:30",
     "WRONG: same error in the guide"),
    ("oaxaca-mexico", "guide",
     "the Santo Domingo museum at 210 pesos and Mitla at 105 pesos. At roughly 18 pesos to the US "
     "dollar, that is about 7 to 27 dollars, 12 dollars, 12 dollars and 6 dollars.",
     "the Santo Domingo museum at 210 pesos and Mitla at 210 pesos. At roughly 18 pesos to the US "
     "dollar, that is about 7 to 27 dollars for the transfer and about 12 dollars for each of the "
     "three sites.",
     "WRONG: budget line propagated the Mitla error"),

    # --- UNSOURCED: colectivo 135/210 and taxi especial 490 to Zone 1 are on the posted tariff;
    # the 715 peso Zone 2 taxi fare could not be sourced.
    ("oaxaca-mexico", "body",
     "while a taxi especial is 490 pesos to Zone 1 and 715 pesos to Zone 2, with rates posted at "
     "the terminal booth",
     "while a taxi especial is 490 pesos to Zone 1 and more to Zone 2, with rates posted at the "
     "terminal booth",
     "UNSOURCED: Zone 2 taxi fare not on any primary tariff"),
    ("oaxaca-mexico", "guide",
     "A private taxi especial from the same booth is 490 pesos to Zone 1 and 715 pesos to Zone 2.",
     "A private taxi especial from the same booth is 490 pesos to Zone 1 and costs more to Zone 2, "
     "so check the posted board before you pay.",
     "UNSOURCED: Zone 2 taxi fare"),

    # --- WRONG: the museum's own practical-information page gives 10am to 6pm, last entry 5.30pm.
    # https://www.museeyslmarrakech.com/en/votre-visite/infos-pratiques/
    ("marrakech-morocco", "body",
     "and the YSL museum 10am to 6.30pm every day except Wednesday",
     "and the YSL museum 10am to 6pm with last entry at 5.30pm, every day except Wednesday",
     "WRONG: closing time overstated by 30 minutes"),

    # --- WRONG: USGS records Mw 6.8 for the Al Haouz earthquake (event us7000kufc). Morocco's
    # CNRST said 7.0. 6.9 matches neither. Date and death toll were correct.
    ("marrakech-morocco", "body",
     "The magnitude 6.9 Al Haouz earthquake of 8 September 2023",
     "The magnitude 6.8 Al Haouz earthquake of 8 September 2023",
     "WRONG: magnitude matched no official source"),

    # --- WRONG (minor): 1991-2020 normals for Marrakesh Menara give a January mean daily maximum
    # of 19.1C, not 19.2C.
    ("marrakech-morocco", "body",
     "against 19.2C in January",
     "against 19.1C in January",
     "WRONG: off by 0.1 against the 1991-2020 normals"),
    ("marrakech-morocco", "guide",
     "averaging a high of 19.2C and a low of 5.9C",
     "averaging a high of 19.1C and a low of 5.9C",
     "WRONG: same figure in the guide"),

    # --- MISLEADING: card 5 + minimum top-up 5 = 10, exactly the 10 dollar cash airport fare. It
    # breaks even on trip one; it does not "pay for itself". https://www.orc.govt.nz/orbus/fares/
    ("queenstown-nz", "body",
     "The Bee Card costs NZ$5 with a NZ$5 minimum top-up and pays for itself on the ride into town, "
     "so buy one immediately.",
     "The Bee Card costs NZ$5 with a NZ$5 minimum top-up, which breaks even against the NZ$10 cash "
     "airport fare on the first trip and saves on every trip after it, so buy one immediately.",
     "MISLEADING: arithmetic does not support 'pays for itself'"),
    ("queenstown-nz", "guide",
     "The Bee Card itself costs NZ$5 and requires a NZ$5 minimum top-up, and it pays back on the "
     "first ride.",
     "The Bee Card itself costs NZ$5 and requires a NZ$5 minimum top-up, which exactly breaks even "
     "against the NZ$10 cash airport fare on the first ride and saves from the second onward.",
     "MISLEADING: same claim in the guide"),

    # --- UNSOURCED: the operator's pricing page does not state a free shuttle.
    ("queenstown-nz", "body",
     "and NZ$507 for a family pass, with a free shuttle from Queenstown included",
     "and NZ$507 for a family pass",
     "UNSOURCED: free shuttle not stated by the operator"),
    ("queenstown-nz", "guide",
     "a family pass covering two adults and two children, with a free Queenstown shuttle included",
     "a family pass covering two adults and two children",
     "UNSOURCED: free shuttle not stated by the operator"),

    # --- UNSOURCED: the 30,000 VND Grab airport surcharge appears only on aggregator sites.
    ("hoi-an-vietnam", "body",
     "Grab runs roughly 250,000 to 350,000 VND plus a 30,000 VND airport surcharge paid directly "
     "to the driver",
     "Grab runs roughly 250,000 to 350,000 VND, plus a small airport pickup surcharge paid directly "
     "to the driver",
     "UNSOURCED: exact surcharge"),
    ("hoi-an-vietnam", "guide",
     "Grab is the cleanest option at roughly 250,000 to 350,000 VND, with a 30,000 VND airport "
     "surcharge handed to the driver on top of the in app fare.",
     "Grab is the cleanest option at roughly 250,000 to 350,000 VND, with a small airport pickup "
     "surcharge handed to the driver on top of the in app fare.",
     "UNSOURCED: exact surcharge"),
    ("hoi-an-vietnam", "tips",
     "From Da Nang airport use Grab at roughly 250,000 to 350,000 VND plus the 30,000 VND airport "
     "surcharge, and refuse touts quoting 500,000 to 700,000 VND.",
     "From Da Nang airport use Grab at roughly 250,000 to 350,000 VND plus a small airport pickup "
     "surcharge, and refuse touts quoting 500,000 to 700,000 VND.",
     "UNSOURCED: exact surcharge"),
]


def main() -> int:
    dry = "--dry-run" in sys.argv
    with open(PATH, encoding="utf-8") as fh:
        data = json.load(fh)
    by_slug = {d["slug"]: d for d in data["destinations"]}

    missing, applied = [], 0
    for slug, field, old, new, reason in PATCHES:
        d = by_slug.get(slug)
        if d is None:
            missing.append(f"{slug}: no such destination")
            continue

        if field == "tips":
            hit = False
            for i, tip in enumerate(d["tips"]):
                if old in tip:
                    d["tips"][i] = tip.replace(old, new)
                    hit = True
            if not hit:
                missing.append(f"{slug}.tips: target not found -> {old[:60]!r}")
                continue
        else:
            target = d["guide"] if field == "guide" else d
            key = "body" if field == "guide" else field
            if old not in target[key]:
                missing.append(f"{slug}.{field}: target not found -> {old[:60]!r}")
                continue
            target[key] = target[key].replace(old, new)

        applied += 1
        print(f"  {slug}.{field}: {reason}")

    if missing:
        print("\nFAILED, no file written:", file=sys.stderr)
        for m in missing:
            print("  - " + m, file=sys.stderr)
        return 1

    if dry:
        print(f"\ndry run: {applied} patches would apply cleanly")
        return 0

    with open(PATH, "w", encoding="utf-8") as fh:
        json.dump(data, fh, indent=2, ensure_ascii=False)
        fh.write("\n")
    print(f"\napplied {applied} corrections to {PATH}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
