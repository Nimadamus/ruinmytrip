#!/usr/bin/env python3
"""Second fact-check patch: Kyoto, Lisbon, Banff, Reykjavik.

Same rules as the first pass. Where a claim was WRONG and a primary source gives the right
figure, the figure is corrected. Where a claim was UNVERIFIABLE, it is rewritten WITHOUT a
replacement number rather than swapped for a second-hand one, because trading an unsourced
number for another unsourced number is not a fix.

Two claims survived scrutiny intact and are deliberately NOT touched: Kyoto's five-tier lodging
tax from 1 March 2026 (200/400/1,000/4,000/10,000 yen at the stated band boundaries) and the
10,000 yen Gion figure. Only the framing of the latter was wrong, not the number.

  python scripts/patch_editorial_factcheck_b.py [--dry-run]
"""
from __future__ import annotations

import json
import sys

PATH = "database/editorial/content.json"

PATCHES = [
    # ---------------- kyoto ----------------
    # UNVERIFIABLE superlative + figure. No dataset produces 48,900 yen, and the one Golden Week
    # 2026 analysis found puts another city higher. Rewritten with no number.
    ("kyoto-japan", "body",
     "Those two colour windows produce the year's highest room rates, and during Golden Week 2026 "
     "Kyoto posted the highest average nightly hotel rate of Japan's major cities at 48,900 yen.",
     "Those two colour windows produce the year's highest room rates, and Kyoto sits among the most "
     "expensive major Japanese cities for a hotel room over Golden Week.",
     "UNVERIFIABLE: hotel-rate superlative and figure"),
    ("kyoto-japan", "guide",
     "Late April and early May bring Golden Week, when Kyoto recorded the highest average hotel rate "
     "of any major Japanese city in 2026 at 48,900 yen a night.",
     "Late April and early May bring Golden Week, when Kyoto room rates run among the highest in the "
     "country and availability collapses.",
     "UNVERIFIABLE: same figure in the guide"),

    # MISLEADING. The property is "Historic Monuments of Ancient Kyoto (Kyoto, Uji and Otsu
    # Cities)": 17 components, not all of them in Kyoto City. Byodo-in and Ujigami are in Uji,
    # Enryaku-ji is in Otsu, Shiga Prefecture. Rewritten to avoid a count I cannot source
    # first-hand (whc.unesco.org returns 403 to automated fetch).
    ("kyoto-japan", "body",
     "The city holds seventeen UNESCO-listed component sites and drew 10.88 million foreign visitors "
     "in 2024",
     "The city is the heart of the Historic Monuments of Ancient Kyoto World Heritage listing, which "
     "also reaches into Uji and Otsu, and drew 10.88 million foreign visitors in 2024",
     "MISLEADING: the 17 components are not all in Kyoto City"),

    # MISLEADING framing. The 10,000 yen figure is real but it is a private penalty set by the
    # Gion-machi Minamigawa District Town Planning Council, a neighbourhood association, not a
    # municipal fine levied by police or the city.
    ("kyoto-japan", "body",
     "Entering the privately owned alleys off Hanamikoji Street carries a 10,000 yen fine, introduced "
     "after repeated incidents of visitors harassing geiko and maiko and intruding on residents.",
     "The narrow lanes off Hanamikoji Street are private property, and the local residents' council "
     "posts a 10,000 yen penalty for entering them, introduced after repeated incidents of visitors "
     "harassing geiko and maiko and intruding on residents. Hanamikoji Street itself remains public.",
     "MISLEADING: private neighbourhood penalty, not a municipal fine"),
    ("kyoto-japan", "tips",
     "Do not step into the private alleys off Hanamikoji Street in Gion, where trespassing now carries "
     "a 10,000 yen fine.",
     "Do not step into the private alleys off Hanamikoji Street in Gion, where the residents' council "
     "posts a 10,000 yen penalty for entering.",
     "MISLEADING: same framing in the tip"),
    ("kyoto-japan", "guide",
     "which now carries real enforcement in Gion alongside the 10,000 yen fine for entering the "
     "private alleys off Hanamikoji",
     "which Gion now enforces, alongside the residents' council's 10,000 yen penalty for entering the "
     "private alleys off Hanamikoji",
     "MISLEADING: same framing in the guide"),

    # MISLEADING and self-defeating. Kyoto City's own page: the Subway & Bus 1-Day Pass "can be used
    # without any additional fare" on the EX buses, cash fare 500 yen. A pass holder never pays the
    # 500 yen, so telling them to avoid the bus to save it is backwards.
    # https://www2.city.kyoto.lg.jp/kotsu/webguide/en/bus/limited_express.html
    ("kyoto-japan", "tips",
     "Take the Karasuma or Tozai subway lines wherever possible instead of the 500 yen EX100 tourist "
     "express, since the 1,100 yen Subway and Bus 1-Day Pass already covers both.",
     "The EX100 and EX101 sightseeing express buses cost 500 yen in cash but run on limited days and "
     "are included at no additional fare in the 1,100 yen Subway and Bus 1-Day Pass, so pass holders "
     "should use them rather than avoid them.",
     "MISLEADING: advice was backwards, the pass already covers the EX buses"),

    # ---------------- lisbon ----------------
    # OUTDATED/WRONG. Portugal's national taxi tariff was rewritten in 2026 onto a combined
    # distance-and-time meter and the luggage supplement was abolished, with meters still being
    # reprogrammed through the summer. Rewritten with no tariff numbers at all: quoting the old
    # ones is wrong and quoting the new ones mid-transition would be wrong again by autumn.
    ("lisbon-portugal", "guide",
     "A taxi to central Lisbon typically runs 15 to 20 euros plus a 1.60 euro luggage surcharge; the "
     "2026 urban meter rate is 0.96 euros per kilometre with a 3.32 euro minimum, rising to 1.21 "
     "euros per kilometre and a 3.98 euro minimum between 21:00 and 06:00 and on weekends and public "
     "holidays.",
     "A metered taxi to central Lisbon is a modest fare rather than a rip-off, but Portugal's national "
     "taxi tariff was rewritten in 2026 onto a combined distance and time meter and meters were still "
     "being reprogrammed through the summer, so check the tariff card posted in the car before you "
     "set off.",
     "OUTDATED: 2026 tariff reform superseded these rates and abolished the luggage supplement"),
    ("lisbon-portugal", "tips",
     "Take the Metro Red Line from Humberto Delgado Airport for 1.90 euros rather than a taxi, which "
     "typically runs 15 to 20 euros plus a 1.60 euro luggage surcharge.",
     "Take the Metro Red Line from Humberto Delgado Airport for 1.90 euros rather than a taxi, which "
     "costs several times more.",
     "OUTDATED: luggage supplement abolished"),

    # WRONG on two counts: the 337 total dates from 1 April 2025, not 2026, and the number added
    # was 62 (275 + 62 = 337). "67" is the streets figure misapplied, and 62 is the inspector
    # count. Rewritten to state only the total, which is not in dispute.
    ("lisbon-portugal", "body",
     "Tuk-tuks are now banned from 337 streets after a further 67 were added on 1 April, with EMEL "
     "reinforcing enforcement with 62 inspectors alongside the municipal police.",
     "Tuk-tuks have been banned from 337 streets since April 2025, with EMEL fielding a dedicated "
     "inspection brigade alongside the municipal police.",
     "WRONG: year, streets-added figure, and inspector count conflated"),

    # WRONG attribution. 67 percent is Alojamento Local's share of Lisbon's total tourist
    # accommodation supply citywide, from the Camara's own justification, not a per-parish
    # AL-to-housing density.
    ("lisbon-portugal", "body",
     "and in Santa Maria Maior the AL density sits near 67 percent",
     "and short-term rentals now account for roughly two thirds of Lisbon's tourist accommodation "
     "supply citywide",
     "WRONG: citywide accommodation share misread as parish housing density"),

    # UNVERIFIABLE. "Documented" implies a published statistic; Portuguese police do not publish
    # pickpocketing by transit line. The underlying advice is sound, the national ranking is not.
    ("lisbon-portugal", "what_ruined",
     "Tram 28E is documented as the most pickpocketed transit line in Portugal",
     "Tram 28E is the worst pickpocket spot most Lisbon visitors will meet",
     "UNVERIFIABLE: no published statistic supports the national superlative"),
    ("lisbon-portugal", "guide",
     "Tram 28E is the highest-risk single location in the city and the most pickpocketed transit line "
     "in Portugal, worked by teams",
     "Tram 28E is the highest-risk single location in the city, worked by teams",
     "UNVERIFIABLE: same superlative"),
    ("lisbon-portugal", "tips",
     "Ride tram 12E or a parallel bus rather than 28E, which is the most pickpocketed transit line in "
     "the country.",
     "Ride tram 12E or a parallel bus rather than 28E, which is where most visitors who get "
     "pickpocketed in Lisbon get pickpocketed.",
     "UNVERIFIABLE: same superlative"),

    # ---------------- banff ----------------
    # MISLEADING by omission. Parks Canada's own shuttle page: "Alpine Start ... Lake Louise
    # Lakeshore <-> Moraine Lake. Paid parking at Lake Louise Lakeshore parking lot. The parking
    # fee is not included in the Alpine Start shuttle ticket." The preceding sentence establishes
    # free Park and Ride parking, so a reader books a 4 a.m. ticket and meets an unexpected fee.
    # https://parks.canada.ca/pn-np/ab/banff/visit/parkbus/louise
    ("banff-canada", "body",
     "and Alpine Start departures at 4 a.m. and 5 a.m.",
     "and Alpine Start departures at 4 a.m. and 5 a.m. that use the Lake Louise Lakeshore lot, where "
     "the paid parking fee applies and is not included in the shuttle ticket",
     "MISLEADING: Alpine Start parking is paid, not the free Park and Ride"),

    # MISLEADING. Contradicted by the article's own guide text: licensed commercial operators,
    # lodge guests, accessible permit holders and cyclists all still reach the lake.
    ("banff-canada", "what_ruined",
     "MDT shuttle release means you simply do not see Moraine Lake.",
     "MDT shuttle release means paying a licensed commercial operator instead, or not seeing Moraine "
     "Lake at all.",
     "MISLEADING: overstates the shuttle's monopoly"),

    # ---------------- reykjavik ----------------
    # MISLEADING as static text. Accurate on the day it was written, but "currently open" on a
    # page nobody updates becomes a false statement the moment the next dike intrusion starts,
    # and the Met Office's stated warning time is as little as twenty minutes.
    ("reykjavik-iceland", "body",
     "The eruption that began on July 16, 2025 ended on August 5, 2025, and while there is no active "
     "eruption at present, magma continues to accumulate beneath Svartsengi, so closures can happen "
     "with little notice even though Grindavik and the Blue Lagoon are currently open.",
     "The most recent eruption ran from July 16 to August 5, 2025, and magma continues to accumulate "
     "beneath Svartsengi, so another is expected at some point with as little as twenty minutes of "
     "warning. Check the Icelandic Met Office and safetravel.is before committing to Grindavik or the "
     "Blue Lagoon, because access to both can close without notice.",
     "MISLEADING: present-tense status on a page that will not update itself"),

    # Internal contradiction: four hours here, four and a half in the guide. Both are defensible
    # (solstice vs monthly average) but not on the same page without saying which.
    ("reykjavik-iceland", "what_ruined",
     "and December gives you roughly four hours of daylight.",
     "and December gives you barely four hours of daylight at the solstice.",
     "MISLEADING: contradicted the guide's four and a half hour figure"),
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
