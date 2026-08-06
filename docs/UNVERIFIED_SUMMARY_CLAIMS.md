# Destination summaries carrying unverified claims

Generated 2026-08-06.

`destinations.summary` is the one-line sentence on every destination card and at the top of every
destination page. For destinations without a risk report it is the ONLY editorial text on the page.

**All 36 are already PUBLIC on production.** They are pre-existing text this branch does not change,
and none of them displays a review date — `summary_reviewed_at` is NULL for every row listed here, so
the site makes no claim that any of it has been checked. That is the intended state: a date on an
unchecked claim is worse than no date.

11 summaries WERE re-verified (`database/risk/summary_audit.json`); none needed correction.

`lisbon-portugal` appears below on purpose. The Lisbon CITY TAX was verified this session, but that is
a different claim from the one production actually displays, so it is excluded from stamping — see
`_excluded_from_stamping` in the audit file.

| # | Destination | Public? | Risk report? | Claim keywords | Summary text |
|---|---|---|---|---|---|
| 1 | `accra-ghana` | LIVE | no | new | Accra rewards travelers willing to navigate Ghana's new e-visa system, real cedi price swings, and airport taxi touts to reach history at Cape Coast and Elmina that still carries weight. |
| 2 | `amsterdam-netherlands` | LIVE | yes | now | Amsterdam is gorgeous and genuinely walkable, but it now carries the highest tourist tax in Europe and is actively running ad campaigns telling certain visitors to stay home. |
| 3 | `banff-canada` | LIVE | no | record, 4.5 million, now | Alberta Rocky Mountain park with turquoise glacial lakes, record 4.5 million annual visitors, and access now controlled by shuttles, paid parking and vehicle bans. |
| 4 | `berlin-germany` | LIVE | no | 2026, 7.5% | Berlin is still one of Europe's cheaper capitals, but 2026 transit fare hikes, a 7.5% overnight tax, and a famously merciless club door policy mean the city no longer runs on easy, spontaneous fun. |
| 5 | `boracay-philippines` | LIVE | no | now | White Beach after a full six-month environmental shutdown and rebuild, now capped and managed instead of left to overrun itself. |
| 6 | `cairo-egypt` | LIVE | no | new, now | The pyramids and the new Grand Egyptian Museum are worth the trip, but expect relentless touts, thick traffic pollution, and a ticketing system that now wants your card, not your cash. |
| 7 | `cancun-mexico` | LIVE | yes | 2026, record | Cancun in 2026 is fighting its worst sargassum year on record and pricier all-inclusive resorts, with real upside limited to the clearer beaches on Isla Mujeres, Costa Mujeres and Holbox. |
| 8 | `cape-town-south-africa` | LIVE | no | now | Cape Town combines Table Mountain, Cape Peninsula beaches and the Winelands with a food scene now anchored by Time Out Market and the V&A Waterfront, but a clear-eyed visitor needs real facts on crime hotspots, airport taxi scams and the city's power and water history before booking. |
| 9 | `cartagena-colombia` | LIVE | no | 50 percent | The walled city is genuinely beautiful, but everything inside it runs 40 to 50 percent above Getsemani prices, and the heat, cruise-day crowds and petty theft are all real, not exaggerated. |
| 10 | `cusco-peru` | LIVE | no | 2026, now | Cusco is the Inca capital turned Andean crossroads city, prized for its stonework, Sacred Valley access and food scene, but a 2026 visit now means navigating stricter Machu Picchu ticketing, real altitude risk and an unpredictable protest corridor along the rail line. |
| 11 | `dubrovnik-croatia` | LIVE | no | now | A genuinely stunning walled city that Game of Thrones turned into a bucket-list stampede, now charging premium euro prices and rationing its own ramparts. |
| 12 | `ho-chi-minh-city-vietnam` | LIVE | no | new, 20 million | Nine million motorbikes, a brand new metro line carrying 20 million passengers a year, and a taxi trade that clones the logos of the companies you were told to trust. |
| 13 | `krakow-poland` | LIVE | no | 1.95 million | Fifteen million visitors a year in a medieval old town the size of a few blocks, an hour from a memorial that 1.95 million people visited last year. |
| 14 | `kyoto-japan` | LIVE | no | now, record, new | Japan's former imperial capital, dense with UNESCO temples and machiya streets, now managing record crowds with new taxes, fines and visitor-priced buses. |
| 15 | `lisbon-portugal` | LIVE | yes | now | Portugal's Atlantic capital, hilly and tiled, still good value in Western Europe, now under short-term rental caps, tuk-tuk bans and heavy pickpocket pressure. |
| 16 | `maldives` | LIVE | no | 2025 | The world's lowest-lying country, sold one island at a time, where the taxes went up in 2025 and the dry-island rules catch people out. |
| 17 | `manila-philippines` | LIVE | no | record, 52 million | An airport that moved a record 52 million people last year into a metro area ranked the 14th most congested on earth. |
| 18 | `milan-italy` | LIVE | no | 2026 | Milan rewards planners and punishes walk-ins: the Duomo rooftop and the Last Supper both require advance booking, and prices spike hard around Fashion Week and the 2026 Olympics. |
| 19 | `naples-italy` | LIVE | no | now | The best-value big city in Italy and the one where you are most likely to lose your phone, sitting next to a Pompeii that now sells a fixed 20,000 tickets a day. |
| 20 | `nassau-bahamas` | LIVE | no | 86.5 per cent, record, 12.5 million | The busiest cruise port in the Caribbean, where 86.5 per cent of the country's record 12.5 million visitors never stay the night. |
| 21 | `new-orleans-usa` | LIVE | no | New | New Orleans pairs French and Spanish colonial architecture, live jazz on nearly every block, and some of the country's best Creole and Cajun food with real, current safety and weather planning issues that visitors should not ignore. |
| 22 | `osaka-japan` | LIVE | no | now | Japan's street-food and nightlife capital, more working-class and less formal than Kyoto, now riding the same weak-yen tourism surge. |
| 23 | `phuket-thailand` | LIVE | no | record, 5.41 million | A record 5.41 million arrivals that did not translate into record revenue, an island running short of tap water, and the most organised jet ski scam in Southeast Asia. |
| 24 | `porto-portugal` | LIVE | no | now | A UNESCO-listed river city that is still genuinely cheap to eat in, and is now taxing and licensing its way through the same overtourism squeeze as Lisbon. |
| 25 | `prague-czechia` | LIVE | no | new, 2026 | Prague pairs a UNESCO listed historic core of Gothic, Baroque and Art Nouveau architecture with one of Europe's best beer and pub cultures, though rising visitor numbers pushed the city into new nightlife curfews, a tourist tax fight and a 2026 transit fare increase. |
| 26 | `punta-cana-dominican-republic` | LIVE | no | record | The Caribbean's busiest airport, eleven million passengers a year, and a seaweed problem that set an all-time Atlantic record the year before you booked. |
| 27 | `queenstown-nz` | LIVE | no | New | Lakeside resort town on Lake Wakatipu in New Zealand's South Island, built around commercial adventure tourism, alpine scenery and winter skiing. |
| 28 | `rome-italy` | LIVE | yes | 2026 | Rome in 2026 pairs an unmatched concentration of ancient and Renaissance sites with a heavily gated, timed entry ticketing system, plus real friction from summer heatwaves, organized pickpocketing, and undisclosed restaurant surcharges. |
| 29 | `santorini-greece` | LIVE | no | 3.4 million, 2025 | Genuinely stunning caldera views, but a 76 sq km island absorbing roughly 3.4 million visitors a year against 15,500 residents, plus a 2025 earthquake swarm that spooked travelers. |
| 30 | `tel-aviv-israel` | LIVE | no | just | World class Mediterranean beaches and food at genuinely high prices, under a US Level 3 travel advisory that demands real planning, not just excitement. |
| 31 | `tokyo-japan` | LIVE | yes | 2026, record, new | Tokyo in 2026 is a bargain for dollar and euro spenders thanks to the weak yen, but record crowds, new fees, and rising fares are chipping away at that value fast. |
| 32 | `ubud-indonesia` | LIVE | no | 2026 | Ubud remains Bali's cultural and wellness capital, prized for its rice terraces, yoga studios and art markets, but 2026 visitors face real friction from traffic congestion, a mandatory tourism levy, rising prices and documented scams. |
| 33 | `vienna-austria` | LIVE | no | 2026 | Vienna delivers on the imperial-palace fantasy, but 2026 brought a discontinued tourist transit pass, a rising visitor tax, and costumed ticket touts working the cathedral square. |
| 34 | `warsaw-poland` | LIVE | no | 1944 | Warsaw was leveled by German forces in 1944 and rebuilt brick by brick into a UNESCO listed Old Town. It also remains one of the cheaper capital cities in the EU. |
| 35 | `zanzibar-tanzania` | LIVE | no | new | Stunning beaches and a UNESCO old town, but a stack of new taxes, a mandatory e-visa, and a real land-rights fight behind the resort boom. |
| 36 | `zurich-switzerland` | LIVE | no | 2026 | Zurich delivers Swiss efficiency and free lake and river swimming, but a franc at an 11 year high against the dollar in 2026 makes nearly everything else genuinely expensive. |

## Why these are not a deploy blocker

Every line here is already live on production today. This branch neither introduces nor amplifies
them; it adds the mechanism to record verification and records it for the 11 that were checked.

## Highest priority for the next pass

1. `amsterdam-netherlands` — "highest tourist tax in Europe" is a superlative Barcelona now rivals.
2. `berlin-germany` — a specific 7.5% overnight tax rate.
3. `cartagena-colombia` — a specific 40-50% price differential.
4. `banff-canada`, `nassau-bahamas`, `krakow-poland`, `manila-philippines`, `phuket-thailand`,
   `santorini-greece` — specific visitor-count statistics.
5. `cancun-mexico` — "worst sargassum year on record".
6. `zurich-switzerland` — "franc at an 11 year high", a fast-moving FX claim.
