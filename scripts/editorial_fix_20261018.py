"""One off: fact corrections from the 2026-10-18 audit (each checked against the official source).
Every old string must occur exactly once in its field, or nothing is written.

  python scripts/editorial_fix_20261018.py [--check]
"""
import json
import sys

BLOG = 'database/editorial/blog.json'
PLACES = 'database/editorial/places.json'

FIX_BLOG = {
    # museosdelbancocentral.org: "Comprar tiquetes" box office; 2026 closures incl. 1, 24, 25, 31 Dec.
    'gold-museum-san-jose-tickets-2026': [
        ('Nothing on the page mentions timed entry, sold out slots or advance booking, which makes this one of the few well known city museums a traveller can still simply walk into.',
         'Tickets can be bought ahead on the museum’s own online box office, but the page mentions no timed entry, so most visitors can still walk in. Check the closure list first: in 2026 the museum shuts on several days, including 1, 24, 25 and 31 December.'),
    ],
    # phuketelephantsanctuary.org/programs/full-day: THB 7,000 adults 13+, THB 3,500 children 4 to 12.
    'phuket-elephant-sanctuary-tickets-2026': [
        ('with no child rate published.', 'with no child rate published for those two programmes, whose prices are for ages 13 and over.'),
        ('Longer and volunteer options are referred to elsewhere on the site without a price attached to them.',
         'The full day programme is priced separately at 7,000 baht for adults aged 13 and over and 3,500 baht for children aged 4 to 12.'),
    ],
    # palazzoducale.visitmuve.it: 1 Apr to 31 Oct 09:00 to 19:00; 1 Nov to 31 Mar 09:00 to 18:00; last entry an hour before.
    'doges-palace-tickets-2026': [
        ('The palace was showing hours of 09:00 to 19:00.',
         'The palace opens 09:00 to 19:00 from April to October and 09:00 to 18:00 from November to March, with last entry an hour before closing.'),
    ],
    # sf.gov release 27 Aug 2026: 24.4 million visitors, $9.94 billion, ADR $266.16. nps.gov/alca: day tour $47.95.
    'san-francisco-2026': [
        ('SF Travel forecasts 24.2 million visitors and 9.9 billion dollars in spending for 2026',
         'SF Travel now forecasts 24.4 million visitors and 9.94 billion dollars in spending for 2026'),
        ('put 2026 at 24.2 million visitors and 9.9 billion dollars in spending',
         'put 2026 at 24.4 million visitors and 9.94 billion dollars in spending'),
        ('Average daily hotel rate in the 2026 forecast is about 258 dollars before those add-ons.',
         'The average daily hotel rate in the updated 2026 forecast is about 266 dollars before those add ons.'),
        ('it is a National Park Service site sold only through Alcatraz City Cruises, it is not on CityPASS, and day tickets in 2026 run about 47 to 51 dollars for adults and sell out weeks ahead.',
         'it is a National Park Service site, the only authorized ferry is Alcatraz City Cruises, and the regular CityPASS does not include it, though a separate CityPASS plus Alcatraz ticket does. An adult day tour is 47.95 dollars in 2026 and sells out weeks ahead.'),
        ('A Clipper tap or contactless card works on Muni, BART, and the cars.',
         'Clipper works on Muni, BART and the cable cars; on the cable cars you can also pay cash or use MuniMobile.'),
    ],
    # sfmta.com cable car page: $9, cash/Clipper/MuniMobile, no tap to pay listed. SF Standard 21 Apr 2026:
    # $12 one way and $18 Cable Car Plus from 4 Jan 2027, one way fare ends 3 Jan 2028. No source for
    # three and seven day cable car tickets going away, so that claim is removed.
    'san-francisco-cable-car-2026': [
        ('and phases out three- and seven-day cable car tickets in favor of an 18 dollar all-day Muni pass.',
         'and adds an 18 dollar all day pass that covers every Muni line.'),
        ('paid in cash, Clipper, MuniMobile, or a contactless card.', 'paid in cash (exact change), Clipper or MuniMobile.'),
        ('Three-day and seven-day cable car tickets go away. The replacement is an 18 dollar cable-car-plus day pass that covers all Muni, with two children free with an adult.',
         'Alongside it comes an 18 dollar Cable Car Plus day pass that covers all Muni for one adult and up to two children. The one way fare is due to end on 3 January 2028, leaving the pass as the main option.'),
        ('For getting around, tap Clipper or a contactless credit card on Muni, BART, and the cars.',
         'For getting around, use Clipper on Muni, BART and the cars.'),
    ],
    # newartdealers.org: NADA 1 to 5 Dec 2026, VIP Tue 1 Dec, 1400 North Miami Avenue (south of Wynwood).
    # inscopeart.com: InScope, from the team behind SCOPE, 1 to 6 Dec 2026, Ocean Drive at 8th Street.
    'miami-art-week-2026': [
        ('The real travel window is 1 to 7 December.', 'The real travel window is 1 to 6 December.'),
        ('<td>NADA Miami</td><td>2 to 6 December</td>', '<td>NADA Miami</td><td>1 to 5 December, VIP preview Tuesday 1 December</td>'),
        ('<td>SCOPE Miami Beach</td><td>2 to 7 December, VIP preview 2 December</td><td>Miami Beach</td>',
         '<td>InScope (from the SCOPE team)</td><td>1 to 6 December</td><td>Ocean Drive at 8th Street, Miami Beach</td>'),
        ('does not close until SCOPE finishes on the 7th. <b>1 to 7 December is the travel window.</b>',
         'does not close until Art Basel and InScope finish on Sunday the 6th. <b>1 to 6 December is the travel window.</b>'),
        ('a Tuesday to Monday trip', 'a Tuesday to Sunday trip'),
        ('Untitled and NADA are open before Art Basel starts', 'Untitled and NADA are open before Art Basel starts (NADA closes on Saturday 5 December)'),
        ('Art Basel, Untitled and SCOPE are on the island.', 'Art Basel, Untitled and InScope are on the island.'),
        ('NADA sits on its northern edge.', 'NADA is close by, at Ice Palace Studios, 1400 North Miami Avenue.'),
        ('Create your Miami trip for 1 to 7 December', 'Create your Miami trip for 1 to 6 December'),
        ('Untitled Art, NADA Miami and SCOPE Miami Beach dates', 'Untitled Art, NADA Miami and InScope dates'),
    ],
    # sf.gov revenue letter 9 Jun 2026: FY 2026-27 General Fund hotel tax 289.4M plus Prop E 36.0M.
    # sf.gov 27 Aug 2026 forecast: occupancy 70.7%, ADR $266.16.
    'san-francisco-hotel-tax-2026': [
        ('with FY 2026-27 budgeted around 312 million, still at 14 percent.',
         'and the June 2026 budget puts the current year, FY 2026-27, at about 325 million, still at 14 percent.'),
        ("SF Travel's 2026 visitor forecast puts average daily rate near 258 dollars, occupancy near 69 percent.",
         "SF Travel's updated 2026 forecast, released in August, puts average daily rate near 266 dollars and occupancy near 71 percent."),
    ],
    # pref.osaka.lg.jp: base includes room only rate, its service charge and compulsory cleaning fee;
    # excludes consumption tax and non lodging services such as meals.
    'osaka-accommodation-tax-2026': [
        ('excluding breakfast and other service charges.',
         'which includes any service charge on the room and any compulsory cleaning fee, but not consumption tax, meals or other extras.'),
    ],
    # lothianbuses.com: adult single 2.20 to 2.40 from 22 Feb 2026. edinburghcastle.scot: adult 23.50 online,
    # 26.00 walk up. No source found for the theft counts or slot length, so those are removed.
    'edinburgh-visitor-levy-2026': [
        ('Lothian Buses adult singles rose to 2.20 pounds on 22 February 2026.',
         'Lothian Buses adult singles rose from 2.20 to 2.40 pounds on 22 February 2026.'),
        ('Edinburgh Castle is about 21.50 to 23.50 pounds online, cheaper than the gate, on 30-minute timed slots.',
         'Edinburgh Castle is 23.50 pounds for an adult booked online against 26 pounds at the gate.'),
        (' Police Scotland logged 47 tourist-related thefts in 2025, down from 62 in 2024. Violent crime against visitors is rare.', ''),
    ],
    # Llei 2/2026 (Catalonia): regional 7.00 five star, 3.40 four star, 4.50 tourist apartments, plus 5 EUR
    # Barcelona surcharge. Greek law 5177/2025 art. 44: per room per night, Nov to Mar 0.50 to 4 EUR.
    # praha1.cz: proposed cap CZK 200 per person per night.
    'tourist-taxes-2026': [
        ('4.50 EUR Catalan regional rate plus 5 EUR city surcharge per person per night',
         'Catalan regional rate of up to 7.00 EUR by category (4.50 EUR for tourist apartments) plus a 5 EUR city surcharge per person per night'),
        ('Prague 1 has proposed about CZK 175 (roughly 7 EUR).', 'Prague 1 has proposed raising the legal cap to CZK 200 per person per night.'),
        ('High season only, April through October.',
         'Charged per room per night all year: these rates apply April through October, then 0.50 EUR (1 to 2 star) to 4 EUR (5 star) from November through March.'),
    ],
}

FIX_PLACES = {
    # zeughauskeller.ch/geschichte: "Unser Haus wurde 1487 erbaut".
    'Zeughauskeller': [
        ('The building is a sixteenth-century arsenal', 'The building is an arsenal built in 1487'),
        ('A genuine sixteenth-century arsenal hall.', 'A genuine fifteenth century arsenal hall.'),
        ('A sixteenth-century arsenal hall by Paradeplatz.', 'An arsenal hall from 1487 by Paradeplatz.'),
        ('in a sixteenth-century arsenal building off Paradeplatz', 'in an arsenal building from 1487 off Paradeplatz'),
        ('in a sixteenth-century arsenal. Go mid-afternoon', 'in an arsenal built in 1487. Go mid-afternoon'),
    ],
}


def apply(item, fixes, label, errors):
    for old, new in fixes:
        hits = [k for k, v in item.items() if isinstance(v, str) and old in v]
        n = sum(item[k].count(old) for k in hits)
        if n != 1:
            errors.append(f'{label}: {n} hits for {old[:60]!r}')
            continue
        item[hits[0]] = item[hits[0]].replace(old, new)


def main():
    check = '--check' in sys.argv
    errors = []
    blog = json.load(open(BLOG, encoding='utf-8'))
    by = {p['slug']: p for p in blog['posts']}
    for slug, fixes in FIX_BLOG.items():
        apply(by[slug], fixes, slug, errors)
    places = json.load(open(PLACES, encoding='utf-8'))
    key = list(places)[0]
    pby = {e['name']: e for e in places[key]}
    for name, fixes in FIX_PLACES.items():
        apply(pby[name], fixes, name, errors)
    if errors:
        print('\n'.join(errors))
        sys.exit(1)
    if not check:
        json.dump(blog, open(BLOG, 'w', encoding='utf-8', newline='\n'), ensure_ascii=False, indent=1)
        json.dump(places, open(PLACES, 'w', encoding='utf-8', newline='\n'), ensure_ascii=False, indent=2)
    print('ok', sum(len(f) for f in FIX_BLOG.values()) + sum(len(f) for f in FIX_PLACES.values()), 'fixes', 'checked' if check else 'written')


if __name__ == '__main__':
    main()
