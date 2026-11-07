# RuinMyTrip keyword research, 2026-11-07

## 1. Method and limits

**Repo read (read only):** app/sitemap.php, app/buddy_landing_pages.php (25 pages: 15 countries, 10 cruise lines), app/editorial_blog.php, database/editorial/blog.json (145 posts, ~105 live; 133 are "budget" tax/fee/ticket posts), database/editorial/content.json (80 cities with `what_ruined`/`safety_rating`), views/ruined.php + `ruined_page()`, and the SEO docs named in the brief plus docs/ACQUISITION_ORGANIC.md.

**What GSC has shown (from the docs):**
- Site impressions fell from about 1,600 per 28 days to **276 in 9/28 to 10/10** after the September 2026 spam update. Clicks were near zero throughout.
- Everything near page 1 is a **fact query**: "san francisco hotel tax rate" (pos 7.8), "city tax milano 2026" (7.5), "city tax nice" (11), "amsterdam city tax" (35), prices and opening hours. No buddy or community query ranks.
- The only brand query is "ruins trip" (pos 19, landing on /login). A search for "ruinmytrip.com" returned no ruinmytrip.com result.

**SERP inspection:** about 30 WebSearch queries on 2026-11-07 (US index), with cited URLs. Limits:
- There was no volume tool, autocomplete or People Also Ask, so **every demand label is a qualitative estimate** based on searcher type, publisher crowding and seasonality.
- WebSearch result order approximates Google's top results. It is not a rank tracker.
- The one public signal: Travel Guard's 2026 survey says 97% of US travelers (93 to 94% globally) regret at least one trip, and 1 in 9 blame the companion ([USA Today via milled](https://milled.com/usa-today/97-of-travelers-regret-a-trip-yUsPRpecR-kDJcXR), [Business Report](https://businessreport.co.za/travel/south-africa/2026-05-06-travel-regret-why-south-africans-just-topped-the-global-holiday-remorse-rankings/)). That is interest in the theme, not search volume.

## 2. Cluster table

| Cluster | Example queries | Intent | Who ranks now (observed) | Difficulty for RMT | Demand (estimate) | Fit with existing assets |
|---|---|---|---|---|---|---|
| Vacation horror stories / travel nightmares | vacation horror stories, travel nightmares, what ruined your vacation | Informational / entertainment | Corporette, Two Wandering Soles, NPR, BuzzFeed, AOL and Uproxx Reddit roundups, The Points Guy staff stories ([1](https://corporette.com/vacation-horror-stories), [2](https://www.buzzfeed.com/hannahdobro/vacations-that-went-horribly-wrong), [3](https://thepointsguy.com/news/tpg-staff-travel-horror-stories/)) | **High** for head terms (aggregators recycle Reddit with high authority). Low only if RMT has a **real story corpus** | Medium, steady, low commercial value | Perfect brand fit, and /ruined exists. But /ruined is **not in sitemap-core** (app/sitemap.php line 85 list), and its lines are mostly editorial ("Researched by us") |
| Worst hotels / resorts / airlines | worst airlines 2026, worst airline complaints, worst resorts in Cancun | Informational, near commercial | DOT-data roundups (AeroCorner, Travel Pirates, PlanetWare, J.D. Power coverage) ([1](https://aerocorner.com/blog/worst-airlines-in-the-us/), [2](https://www.planetware.com/2248551/american-airlines-worst-airline-overall-2026-largest-america/)); TripAdvisor for named hotels | High, plus defamation and fact risk on named properties | High for airlines (news spikes) | Weak. No airline data. Do not build |
| Generic travel scams | common tourist scams, travel scams 2026 | Informational | Expert Vagabond, Pacsafe, Business Insider, Fox, Cheapflights ([1](https://expertvagabond.com/common-travel-scams/), [2](https://int.pacsafe.com/blogs/pacsafe-blog/common-tourist-scams-around-the-world)) | High (saturated evergreen listicles) | High | Brand fit, no data edge |
| Destination scams | barcelona scams, marrakech scams medina, rome scams colosseum | Informational, pre-trip | Niche blogs (Solo Sophie, MarocMama, Indie Traveller, The Roman Guy), World Nomads, local tabloids, plus **thin AI scam-guide sites** (tabiji, airconnect, italyplanner.ai, barcatrips) ([1](https://www.solosophie.com/tourist-scams-barcelona/), [2](https://www.worldnomads.com/travel-safety/north-africa/morocco/moroccan-scams-and-rip-offs), [3](https://italyplanner.ai/scams-rome-guide.html)) | **Medium** per city. Blogs win on first-hand detail. Scaled AI pages are the pattern the spam update targets | Medium per city, high for the top 10 cities | Strong: 80 city entries already carry `what_ruined` and `safety_rating`. Only for cities where RMT has real material |
| Time-sensitive booking scams | booking.com whatsapp scam, booking.com verify card message | Informational, urgent | Newstalk, IdentityIQ, tuenews, vendor blogs (tripprof) ([1](https://www.newstalk.com/news/booking-com-2280525), [2](https://www.identityiq.com/articles/booking-com-whatsapp-scam-2026)) | Medium, but needs news speed and a stable fact base | Spiky, high during breach news | Fits /safety and blog "safety" (only 2 posts today) |
| Tourist traps / "is X worth it" | is the eiffel tower worth it, is santorini overrated, tourist traps europe reddit | Informational, decision | Paris niche blogs (World in Paris, Solo Sophie), islands.com, Reddit threads, BuzzFeed/Yahoo r/AskEurope roundups, Rick Steves forum ([1](https://worldinparis.com/is-it-worth-going-up-the-eiffel-tower), [2](https://travellemming.com/skip-santorini-overpriced-overcrowded-overrated/), [3](https://www.yahoo.com/lifestyle/europeans-sharing-27-tourist-traps-091602856.html)) | Medium. Reddit dominates "real opinion" intent; RMT's equivalent is traveler ratings, and there are **zero community reviews today** | Medium to high for famous attractions | Good on the ~80 editorial /p/ pages (Louvre, Eiffel ticket post, Sagrada Familia, Anne Frank) |
| Complaints and refunds (EU261, DOT, chargebacks) | eu261 compensation claim, dot refund rule cancelled flight, hotel chargeback, airbnb host cancelled refund | Informational, transactional (claim) | Claim firms (AirHelp, AirAdvisor, ClaimCompass), TPG, Elliott.org, NerdWallet, **transportation.gov**, ECC national pages ([1](https://www.airhelp.co.uk/eu-261-compensation/), [2](https://www.transportation.gov/individuals/aviation-consumer-protection/refunds?os=io.), [3](https://www.elliott.org/the-troubleshooter/how-to-solve-this-mystery-of-the-missing-hotel-refund-check-your-credit-card), [4](https://nerdwallet.com/article/travel/airbnb-canceled)) | **High**: YMYL money topic, claim firms buy links, government pages rank | High | Brand fit only. No claims product. One hub page at most |
| Lost luggage / denied boarding | montreal convention lost luggage limit, overbooked flight compensation us | Informational, transactional | ClaimCompass, AirHelp, the Canadian Transportation Agency, service-public.fr ([1](https://claimcompass.eu/blog/how-much-lost-luggage-compensation), [2](https://protection-passager-passenger.otc-cta.gc.ca/en/refunds-and-compensation/compensation-for-lost-damaged-and-delayed-baggage)) | High. Note the SERP itself is inconsistent: some pages still cite 1,288 SDR, others the newer 1,519 SDR cap | Medium to high | Fits the "verified numbers" house style, the same skill as the tax posts |
| Travel safety / advisories | is it safe to travel to mexico, mexico travel advisory 2026 | Informational, YMYL | **travel.state.gov**, local TV news syndication, TravelPulse ([1](https://travel.state.gov/en/international-travel/travel-advisories/mexico.html?lv=true), [2](https://www.travelpulse.com/news/destinations/us-updates-mexico-travel-advisory-ahead-of-2026-world-cup)) | **Very high** (government plus news, YMYL) | High | Exists editorially (tehran-2026, rio-de-janeiro-2026). Do not chase as a cluster |
| Common travel mistakes / lessons learned | travel mistakes first time abroad, travel mistakes to avoid | Informational | TPG, ThePlanetD, Dave's Travel Pages, insurers, many listicles ([1](https://thepointsguy.com/guide/first-international-trip-tips-tricks-mistakes-to-avoid), [2](https://www.davestravelpages.com/common-travel-mistakes/)) | High (generic, saturated) | High | Only via aggregated "what ruined it" data |
| Travel buddy / companion finder | find a travel buddy, travel buddy app, travel partner website | Commercial / navigational | GAFFL, Workaway buddy section, JoinMyTrip, WeRoad, roundup posts (heyexplorer, Cheapflights) ([1](https://gogaffl.com/blog_posts/how-to-find-a-travel-buddy), [2](https://www.workaway.info/find-travelbuddy.html), [3](https://www.joinmytrip.com/en/find-travel-buddy)) | High for head terms (established products with UGC) | Medium | Core product (/buddies, the how-to-find-a-travel-buddy-safely post) |
| Country travel buddy | travel buddy japan, travel partner thailand | Commercial | Old forum/board pages (Kanpai Japan's "isshoni" board, posts from 2017 to 2024) ([1](https://www.kanpai-japan.com/isshoni-travel-companions?page=13)) | **Low to medium**: the SERP is stale, and fresh dated posts beat it **if listings exist** | Low to medium per country | /travel-buddies/{country} (15). Value depends on real open trips |
| Cruise buddies / cabin mates | cruise buddy, cruise companion, cabin mate solo cruise | Commercial | Cruise Critic forum threads, Fodor's forum, Cruiseline.com, SinglesCruise, VacationAffairs ([1](https://boards.cruisecritic.co.uk/topic/2337914-how-to-find-cruising-buddy/), [2](https://www.vacationaffairs.com/cruises)) | **Medium**: forum threads, no dominant product. Per-line roll calls belong to Cruise Critic | Low to medium (niche, older solo cruisers) | /cruise-buddies/{line} (10) |
| Tourist tax / city tax | milan tourist tax 2026, lisbon tourist tax 2026, amsterdam tourist tax 2026, tourist tax calculator | Informational (a number) | Hostaway, Il Sole 24 Ore, Italian press (QuiFinanza, SiViaggia), immobiliare.it, Airbnb help, mappr.co, Which?, amsterdam-explorer (has a calculator), Holidu calculator ([1](https://www.hostaway.com/vacation-rental-guide/tourist-tax/italy/milan/), [2](https://www.mappr.co/europe-tourist-taxes/), [3](https://amsterdam-explorer.com/tourist-tax/), [4](https://www.holidu.co.uk/magazine/global-city-tourist-tax)) | **Low to medium**. Mostly host/vendor pages, often stale or contradictory (one Lisbon roundup still says EUR 1). RMT already ranks 7 to 10 | Medium per city, seasonal (spring booking), growing as rates rise | **Strongest asset**: about 75 posts plus /tourist-tax-calculator |
| Entry fees / tickets | venice access fee 2026 dates, park guell ticket price | Informational, transactional | Official portals (cda.ve.it), The Local, Rick Steves, Wego ([1](https://cda.veneziaunica.it/en/access-fee), [2](https://www.thelocal.it/20251209/calendar-when-will-you-need-to-pay-a-fee-to-visit-venice-in-2026)) | Medium. The official site wins the head term; there is room on "worked cost" intent | Medium, seasonal | ~45 ticket posts, venice-access-fee-2026 |

## 3. Top 15 prioritized opportunities

Ranked by win odds × fit. "New" always means **one** page. Each entry gives queries, the page, the edge needed, and its links.

1. **Refresh tax posts already ranking** (Milan, San Francisco, Nice, Amsterdam, Porto)
   - Queries: "[city] tourist tax 2026", "city tax [city]", "[city] hotel tax rate".
   - Page: existing /blog/{city}-tourist-tax-2026.
   - Edge: official municipal source, a "last checked" date, the worked per-night cost, and a prefilled calculator at the top. Rival pages contradict each other on Milan's April reset and Amsterdam's 2027 plan.
   - Links: /tourist-tax-calculator?city=X, /d/{city}, /blog/tourist-taxes-2026.
2. **/tourist-tax-calculator as the hub**
   - Queries: "tourist tax calculator", "city tax calculator europe".
   - Page: existing.
   - Edge: rivals are Holidu's calculator and amsterdam-explorer's one-city tool. RMT can be multi-city, with a source and check date shown per rate.
   - Links: every tax post, footer, /d/{city} fact rows.
3. **Get ahead of "2027" queries**
   - Queries: "amsterdam tourist tax 2027", "[city] tourist tax increase".
   - Page: add a dated 2027 section to the 2026 posts in place. No new URLs.
   - Edge: speed plus official sources (Amsterdam's 16% for 2027 is proposed, not yet confirmed).
   - Links: /blog/tourist-taxes-2026.
4. **/blog/tourist-taxes-2026 as the comparison page**
   - Queries: "tourist taxes europe 2026", "most expensive tourist tax".
   - Page: existing (pos 5.7).
   - Edge: a sortable table with a per-night cost for one stated example room, each rate sourced. mappr.co and Which? have maps but no worked costs.
   - Links: all tax posts, the calculator.
5. **Entry fees with worked cost** (Venice access fee, Skytree, Park Guell, Sagrada Familia, Eiffel)
   - Queries: "venice access fee 2026 dates", "do I pay venice fee if staying overnight", "[attraction] tickets 2026".
   - Page: existing posts.
   - Edge: exemption logic and the official booking link. Refresh only where a check is due.
   - Links: /p/{attraction}, /d/{city}.
6. **Destination scams, only for 5 to 8 cities with real material** (Barcelona, Rome, Marrakech, Paris, Bangkok, Istanbul, Lisbon, Amsterdam)
   - Queries: "[city] tourist scams", "[city] tourist traps".
   - Page: an anchored "Scams and traps" section on /d/{city}. A standalone guide only where there are 800 or more genuinely specific words.
   - Edge: named streets and venues, tourist police contacts, a scam's legal status (e.g. Rome's gladiator photo ban), and community lines when they exist. Avoid the template pattern: the SERP already holds scaled AI scam sites (tabiji, airconnect, italyplanner.ai).
   - Links: /ruined?d=city, /travel-buddies/{country}.
7. **Index /ruined properly**
   - Queries: "what ruined your trip", "travel regrets", "vacation went wrong", brand.
   - Page: existing /ruined. It is indexable but missing from the sitemap-core list (app/sitemap.php line 85).
   - Edge: real first-person lines that link to places, plus the regret survey for context. Grow it through the review flow; most current lines are editorial.
   - Links: homepage, every /d/{city}, footer.
8. **Country buddy pages where the SERP is stale** (Japan, Thailand, Vietnam, Peru, Colombia, Morocco)
   - Queries: "travel buddy [country]", "travel partner [country] [month]".
   - Page: existing /travel-buddies/{country}.
   - Edge: dated open trips. The Japan SERP is a board of 2017 to 2024 posts. A page with no live trips will not rank, so keep its empty state honest.
   - Links: /buddies, /d/{cities}, the safety post.
9. **Cruise companion / cabin mate**
   - Queries: "cruise buddy", "solo cruise companion", "cabin mate cruise".
   - Page: existing /buddies/cruise as the hub, the 10 line pages as they are.
   - Edge: dated sailing matches and safety rules. The current answer is old Cruise Critic and Fodor's threads.
   - Links: /cruise-buddies/{line}, /safety.
10. **/blog/how-to-find-a-travel-buddy-safely**
    - Queries: "how to find a travel buddy", "travel with a stranger safe", "travel buddy red flags".
    - Page: existing.
    - Edge: RMT's real mechanics (accept before messaging, 18+ to post, public first meetup). Rivals are app roundups.
    - Links: /buddies, /safety, the 25 landing pages.
11. **One "Trip went wrong: what you can claim" hub**
    - Queries: "how to complain to an airline", "eu261 claim denied", "dot refund cancelled flight", "hotel chargeback".
    - Page: **one new** page.
    - Edge: a decision tree linking primary sources (transportation.gov, national EU261 bodies, the CTA, card dispute windows). Claim firms own the long tail; do not chase it.
    - Links: from /ruined, /safety.
12. **Lost luggage limit, verified**
    - Queries: "montreal convention lost luggage limit", "lost luggage compensation how much".
    - Page: a section of #11, or one post if #11 earns impressions.
    - Edge: the 1,519 SDR figure with its effective date and source. Many ranking pages still say 1,288 SDR.
    - Links: #11.
13. **Booking-platform message scams**
    - Queries: "booking.com whatsapp scam", "booking.com verify card message".
    - Page: one evergreen safety post, updated in place.
    - Edge: a checklist that does not depend on any one breach ("is the message in the app inbox?"), plus official reporting links (FTC, Action Fraud).
    - Links: /safety, /ruined.
14. **"Is [attraction] worth it" on existing editorial /p/ pages** (Louvre, Eiffel, Sagrada Familia, Anne Frank House, Book of Kells)
    - Queries: "is the louvre worth it", "[attraction] overrated".
    - Page: existing /p/{slug}. Add a "Worth it if / skip if" block.
    - Edge: price, time and queue facts already verified for the page. Traveler ratings will strengthen it later.
    - Links: the matching ticket post, /d/{city}.
15. **Brand capture**
    - Queries: "ruinmytrip", "ruin my trip", "ruins trip".
    - Page: homepage, /about.
    - Edge: an unambiguous brand title and Organization schema. Do not touch /login.

## 4. Keyword-to-page map (existing important URLs)

| URL | Primary queries | Secondary | Note |
|---|---|---|---|
| /tourist-tax-calculator | tourist tax calculator, city tax calculator | how much tourist tax per night | Hub for all tax posts |
| /blog/tourist-taxes-2026 | tourist taxes 2026, tourist tax europe 2026 | most expensive tourist tax | Comparison table |
| /blog/milan-tourist-tax-2026 | milan tourist tax 2026, city tax milano 2026 | tassa di soggiorno milano | Snippet test running, measure 2026-11-25 |
| /blog/san-francisco-hotel-tax-2026 | san francisco hotel tax rate | sf TOT, tourism improvement district | Best ranker. Leave the content alone |
| /blog/amsterdam-tourist-tax-2026 | amsterdam tourist tax 2026, amsterdam city tax | amsterdam tourist tax 2027 | Currently /d/amsterdam ranks for it (pos 35). Link the city page to the post |
| /blog/nice-tourist-tax-2026 | nice tourist tax, city tax nice | taxe de sejour nice | /d/nice-france ranks at 11; make sure it links prominently |
| /blog/venice-access-fee-2026 | venice access fee 2026 dates, venice entry fee | do i need to pay venice fee overnight | Official site holds #1 |
| /blog/{attraction}-tickets-2026 | [attraction] tickets 2026, [attraction] price | [attraction] opening hours | One per attraction already; no more |
| /p/{editorial slug} (80) | [place] hours, [place] price, [place] worth it | [place] tickets | Add "worth it" block (#14) |
| /d/{city} (85) | [city] travel tips, [city] scams, solo travel [city] | [city] city tax (route to the post) | Scams section only where real |
| /ruined | what ruined your trip, travel regrets, trip went wrong | ruin my trip (brand) | Add to sitemap-core |
| /buddies | find a travel buddy, travel buddy finder | travel companion website | Head terms are hard |
| /travel-buddies/{country} (15) | travel buddy [country], travel partner [country] | solo travel [country] meet people | Index value tied to live trips |
| /buddies/cruise and /cruise-buddies/{line} (10) | cruise buddy, cruise companion, [line] solo cruise buddy | cabin mate | Forum-dominated SERP |
| /blog/how-to-find-a-travel-buddy-safely | how to find a travel buddy safely | travel with a stranger safety | |
| /safety | travel buddy safety, meeting travelers safely | booking scam message | Hub for #13 |
| /map | travel map, [city] map places | | Low search value; keep for users |

## 5. Do not build

- **Programmatic "[city] tourist scams" × 85 cities**, or "[place] scams" × 1,200 places. The SERP already shows scaled AI scam sites (tabiji, airconnect.live, italyplanner.ai); copying them is the exact pattern the September 2026 update hit.
- **"[city] tourist tax 2027" duplicate URLs** next to the 2026 posts. Update in place, so there is one URL per city.
- **More templated ticket/fee posts** for minor attractions with no demand signal. 133 "budget" posts is already a heavy, pattern-like footprint. Expand only where GSC shows impressions.
- **"Travel buddy [city]" pages for all 85 cities**, or more cruise lines and countries beyond the 25. The landing file itself says these were hand-picked; any more becomes doorway pages, especially with no live trips.
- **"Worst hotels in [city]" / "worst resorts" / "worst airlines" rankings** without RMT's own data. Defamation risk on named properties, and big DOT-data publishers own the airline terms.
- **Per-country "is it safe to travel to X" pages.** YMYL, government pages rank, and stale advice is a liability. Keep the few existing editorial ones accurate and dated.
- **Long-form EU261/DOT/chargeback legal guides per airline or country** (AirHelp-style "[airline] compensation" pages). One hub that links primary sources only.
- **Fabricated or house-written "horror stories" presented as traveler stories,** or padding /ruined with editorial lines to make it look busy. That is fake UGC, and the page's value comes from real people.
- **Thin /p/ basics pages in the sitemap** (the 98 tier-3 pages in SITEMAP_PROPOSAL_20261102). Keep them out, as that proposal says.
- **Title and meta churn across many pages at once.** The 9-page title test lost. Change one thing at a time against a control, as docs/SEO_TITLE_TEST_RESULT_20261011.md does.
