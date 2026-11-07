# City scam title test (starts on the deploy after 2026-11-07)

**Change:** the 16 city pages that carry a sourced "Scams and traps" section get
`{City} scams, tourist traps and costs (2026) | RuinMyTrip` and a snippet that says the scams are
sourced from official travel advice. The other 69 city pages keep the control title
`{City} 2026: costs, tickets, taxes and what nearly ruins it | RuinMyTrip`.
Code: `rmt_destination_scam_title()` in app/seo.php. **Off:** `RMT_SCAM_TITLE_TEST = false` (one line,
no other change). No URL, canonical, robots or sitemap change.

**Why this is not the September test again:** that title promised "travelers going there" that the
pages did not have, and lost. This one promises exactly the section the page now holds (16 cities,
95 items, each with a government or official source). Keyword research (docs/research_20261107/KEYWORDS.md)
ranks "[city] scams" as medium demand per city, with a results page that already holds scaled AI scam
sites, which a sourced section can beat.

**Test group (16):** amsterdam-netherlands, bangkok-thailand, barcelona-spain, cairo-egypt,
cancun-mexico, ho-chi-minh-city-vietnam, istanbul-turkiye, jaipur-india, lisbon-portugal,
marrakech-morocco, mexico-city-mexico, paris-france, prague-czechia, rio-de-janeiro-brazil,
rome-italy, seminyak-bali-indonesia. **Control:** every other `/d/{slug}`.

**Measure:** Search Console, page × date for `^https://ruinmytrip.com/d/[^/?]+$`, 28 days before the
deploy against 28 days after, test against control (difference in differences, as in
docs/SEO_TITLE_TEST_RESULT_20261011.md). Also read queries containing "scam" for the test pages.
**Loss rule:** if test impressions fall relative to control over 28 days, switch it off.
**Note:** the title change for reviews and guides (title_fix_baseline.csv) shipped on 2026-11-07 and
does not touch `/d/` pages, so the two do not confound each other.
