# RuinMyTrip sitemap proposal, 2026-11-02

Status: PROPOSAL ONLY. Nothing in app/sitemap.php, app/indexability.php, canonical/robots code or database/sitemap_places.txt was changed. Nothing was submitted or pinged to Google. Raw per URL data: docs/sitemap_audit_20261102.csv (182 rows, one per candidate URL, with tier and reason).

## 1. Method

1. Inputs: tools/rmt_ops/rel_1029/sitemap_review_pages_84.csv (82 "A include" + 2 "B improve first"), sitemap_candidates_182.csv, database/sitemap_places.txt on main.
2. Live sitemaps: GET https://ruinmytrip.com/sitemap.xml and every child sitemap on 2026-11-02 (Pacific morning).
3. Live pages: GET each of the 182 candidate URLs once (redirects not followed, about 1.7 req/s, desktop Chrome UA). Parsed: HTTP status, X-Robots-Tag, robots meta, canonical, title, meta description; word counts of the three blocks that are unique to a page (the "RuinMyTrip guide to X" section, the Official Review article under "From the RuinMyTrip team", traveler review articles under #reviews); data signals from "The basics" card (address, phone, website, 7 day hours) plus JSON-LD GeoCoordinates; photos from img tags.
4. Duplication: exact duplicate titles and descriptions; titles/descriptions templated by replacing place name and city; 5 word shingle Jaccard of unique text against every other candidate; guide vs Official Review overlap on the same page.
5. Red flags: regex scan of unique text for 2023/2024/2025, "temporarily", "renovat", "reopen", "no longer", "currently"; chain brand names; social media URL as the website; Review/Rating JSON-LD on pages without a traveler review.
6. Google Search Console (read only, service account via tools/gsc_submit.py, webmasters.readonly scope): Search Analytics by page for 2025-07-01..2026-11-01, last 90 days, last 28 days; URL Inspection API (read only) for all 182 URLs: coverage state and Google selected canonical.
7. Eligibility rules from code: rmt_indexable('place') needs public status, a destination and one real signal (address, website, phone, hours, review, photo or editorial); the places group in app/sitemap.php only emits slugs on the allowlist file that also pass rmt_indexable.

Not checked: full external fact check of every price and hour on the 84 editorial pages (only flagged phrases were reviewed; the allowlist header says facts were checked on 2026-10-29/30); mobile rendering; Core Web Vitals; backlinks.

## 2. Current live state (fetched 2026-11-02)

| Child sitemap | URLs |
|---|---|
| sitemap-core.xml | 54 |
| sitemap-destinations.xml | 85 |
| sitemap-places.xml | 82 |
| sitemap-blog.xml | 105 |
| sitemap-guides.xml | 82 |
| sitemap-profiles.xml | 1 |
| Total | 409 |

* "264 proposed URLs" is not 264 distinct pages. The 82 "review pages" (tier A of the 84 file) are exactly the 82 slugs in database/sitemap_places.txt, and all 84 review pages are inside the 182 place candidates. Distinct candidate URLs: **182**.
* sitemap-places.xml contains exactly those **82** URLs (set equality verified). So 82 of 182 candidates are already submitted; **100** are not (2 held editorial pages + 98 basics only pages). There is no community sitemap child live.
* All 182 return 200, `index, follow`, no X-Robots-Tag, self canonical. Google selected canonical matched ours on every inspected URL.
* GSC coverage: 148 "Submitted and indexed", 33 "URL is unknown to Google", 1 "Discovered, currently not indexed". Of the 82 live sitemap pages, 70 are indexed and 12 are not yet: alte-pinakothek-munich, capitoline-museums-rome, gundel-budapest, hofbrauhaus-munich, jewish-museum-berlin, joe-s-stone-crab-miami, katz-s-delicatessen-new-york-city, nationalmuseum-stockholm, nijo-castle-kyoto, oceanario-de-lisboa-lisbon, tokyo-national-museum, warsaw-rising-museum. Of the 98 basics pages, 76 are already indexed (from the older catalog wide sitemap).
* GSC impressions (16 months): the 82 live pages 1,072 total; the other 100 pages 157 total; 1 click across all 182. No basics only page reaches 50 impressions (max 23, pizzeria-ai-marmi-rome).
* No candidate has a traveler review and none has a photo. Unique content on the 84 editorial pages = Official Review (76 to 115 words) + guide (282 to 650 words). The 98 basics pages have 0 unique words, and all 98 descriptions follow one template ("X is a Y in Z. address. Open ... Phone, website and directions on the page.").
* No exact duplicate titles or descriptions; max unique text overlap between any two candidates is under 0.2 (no near duplicates); guide vs Official Review overlap on a page is at most 0.33.

## 3. Totals

| Tier | Count | Already in live sitemap |
|---|---|---|
| Tier 1 include now | 80 | 80 |
| Tier 2 include after named fix | 4 | 2 |
| Tier 3 keep out (6 of them noindex candidates) | 98 | 0 |

Recommendation: keep the places sitemap at the current 82 (80 clean + 2 needing a fact recheck), grow it to 84 once the two held pages are fixed, and add **none** of the 98 basics only pages. No change to sitemap or canonical settings is needed today.

## 4. Tier 1: include now (80)

Unique editorial content (Official Review + structured guide), indexable, self canonical, 3+ data signals. All already in the live sitemap: keep.

| # | URL | Live sitemap | GSC impr 16mo | Coverage | Reason |
|---|---|---|---|---|---|
| 1 | /p/book-of-kells-experience-at-trinity-college-dublin | yes | 188 | Submitted and indexed | ALREADY LIVE; Official Review 85w + guide 572w, 5 data signals, self canonical |
| 2 | /p/ekstedt-stockholm | yes | 140 | Submitted and indexed | ALREADY LIVE; Official Review 93w + guide 310w, 4 data signals, self canonical |
| 3 | /p/zeughauskeller-zurich | yes | 65 | Submitted and indexed | ALREADY LIVE; Official Review 90w + guide 302w, 5 data signals, self canonical |
| 4 | /p/vasa-museum-stockholm | yes | 64 | Submitted and indexed | ALREADY LIVE; Official Review 95w + guide 451w, 5 data signals, self canonical |
| 5 | /p/hungarian-national-museum-budapest | yes | 57 | Submitted and indexed | ALREADY LIVE; Official Review 85w + guide 463w, 4 data signals, self canonical |
| 6 | /p/museo-nacional-de-antropologia-mexico-city | yes | 48 | Submitted and indexed | ALREADY LIVE; Official Review 88w + guide 382w, 3 data signals, self canonical (only 3 data signals: add hours/phone) |
| 7 | /p/museum-of-cycladic-art-athens | yes | 38 | Submitted and indexed | ALREADY LIVE; Official Review 85w + guide 370w, 5 data signals, self canonical |
| 8 | /p/au-pied-de-cochon-paris | yes | 28 | Submitted and indexed | ALREADY LIVE; Official Review 86w + guide 318w, 4 data signals, self canonical |
| 9 | /p/st-john-london | yes | 25 | Submitted and indexed | ALREADY LIVE; Official Review 83w + guide 365w, 4 data signals, self canonical |
| 10 | /p/cafe-tortoni-buenos-aires | yes | 24 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 320w, 5 data signals, self canonical |
| 11 | /p/le-procope-paris | yes | 23 | Submitted and indexed | ALREADY LIVE; Official Review 86w + guide 302w, 5 data signals, self canonical |
| 12 | /p/anne-frank-house-amsterdam | yes | 21 | Submitted and indexed | ALREADY LIVE; Official Review 91w + guide 591w, 4 data signals, self canonical |
| 13 | /p/topography-of-terror-berlin | yes | 21 | Submitted and indexed | ALREADY LIVE; Official Review 78w + guide 477w, 4 data signals, self canonical |
| 14 | /p/museo-frida-kahlo-mexico-city | yes | 17 | Submitted and indexed | ALREADY LIVE; Official Review 88w + guide 370w, 4 data signals, self canonical |
| 15 | /p/national-museum-of-scotland-edinburgh | yes | 17 | Submitted and indexed | ALREADY LIVE; Official Review 77w + guide 460w, 4 data signals, self canonical |
| 16 | /p/national-archaeological-museum-athens | yes | 16 | Submitted and indexed | ALREADY LIVE; Official Review 90w + guide 462w, 4 data signals, self canonical |
| 17 | /p/natural-history-museum-london | yes | 16 | Submitted and indexed | ALREADY LIVE; Official Review 89w + guide 523w, 4 data signals, self canonical |
| 18 | /p/noma-copenhagen | yes | 16 | Submitted and indexed | ALREADY LIVE; Official Review 92w + guide 383w, 5 data signals, self canonical |
| 19 | /p/tate-modern-london | yes | 16 | Submitted and indexed | ALREADY LIVE; Official Review 89w + guide 569w, 5 data signals, self canonical |
| 20 | /p/roscioli-rome | yes | 13 | Submitted and indexed | ALREADY LIVE; Official Review 93w + guide 339w, 3 data signals, self canonical (only 3 data signals: add hours/phone) |
| 21 | /p/calouste-gulbenkian-museum-lisbon | yes | 12 | Submitted and indexed | ALREADY LIVE; Official Review 86w + guide 386w, 4 data signals, self canonical |
| 22 | /p/van-gogh-museum-amsterdam | yes | 12 | Submitted and indexed | ALREADY LIVE; Official Review 83w + guide 495w, 5 data signals, self canonical |
| 23 | /p/louvre-museum-paris | yes | 11 | Submitted and indexed | ALREADY LIVE; Official Review 90w + guide 647w, 4 data signals, self canonical |
| 24 | /p/7-portes-barcelona | yes | 10 | Submitted and indexed | ALREADY LIVE; Official Review 85w + guide 302w, 5 data signals, self canonical |
| 25 | /p/berlinische-galerie | yes | 10 | Submitted and indexed | ALREADY LIVE; Official Review 83w + guide 440w, 4 data signals, self canonical |
| 26 | /p/cafe-savoy-prague | yes | 10 | Submitted and indexed | ALREADY LIVE; Official Review 92w + guide 316w, 5 data signals, self canonical |
| 27 | /p/de-kas-amsterdam | yes | 10 | Submitted and indexed | ALREADY LIVE; Official Review 86w + guide 327w, 4 data signals, self canonical |
| 28 | /p/museo-larco-lima | yes | 10 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 369w, 5 data signals, self canonical |
| 29 | /p/upper-belvedere-vienna | yes | 10 | Submitted and indexed | ALREADY LIVE; Official Review 86w + guide 530w, 5 data signals, self canonical |
| 30 | /p/acropolis-museum-athens | yes | 9 | Submitted and indexed | ALREADY LIVE; Official Review 88w + guide 517w, 5 data signals, self canonical |
| 31 | /p/malba-buenos-aires | yes | 9 | Submitted and indexed | ALREADY LIVE; Official Review 79w + guide 359w, 4 data signals, self canonical |
| 32 | /p/museum-of-the-future-dubai | yes | 9 | Submitted and indexed | ALREADY LIVE; Official Review 91w + guide 344w, 5 data signals, self canonical |
| 33 | /p/oskar-schindler-s-enamel-factory-krakow | yes | 9 | Submitted and indexed | ALREADY LIVE; Official Review 94w + guide 430w, 4 data signals, self canonical |
| 34 | /p/new-york-botanical-garden-new-york-city | yes | 8 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 518w, 4 data signals, self canonical |
| 35 | /p/westminster-abbey-london | yes | 7 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 648w, 3 data signals, self canonical (only 3 data signals: add hours/phone) |
| 36 | /p/brasserie-lipp-paris | yes | 6 | Submitted and indexed | ALREADY LIVE; Official Review 79w + guide 291w, 5 data signals, self canonical |
| 37 | /p/buckingham-palace-state-rooms-london | yes | 6 | Submitted and indexed | ALREADY LIVE; Official Review 94w + guide 641w, 5 data signals, self canonical |
| 38 | /p/design-museum-denmark-copenhagen | yes | 6 | Submitted and indexed | ALREADY LIVE; Official Review 85w + guide 384w, 4 data signals, self canonical |
| 39 | /p/duomo-di-milano | yes | 5 | Submitted and indexed | ALREADY LIVE; Official Review 86w + guide 399w, 5 data signals, self canonical |
| 40 | /p/figlmuller-vienna | yes | 5 | Submitted and indexed | ALREADY LIVE; Official Review 89w + guide 322w, 5 data signals, self canonical |
| 41 | /p/antiche-carampane-venice | yes | 3 | Submitted and indexed | ALREADY LIVE; Official Review 91w + guide 362w, 4 data signals, self canonical |
| 42 | /p/macba-barcelona | yes | 3 | Submitted and indexed | ALREADY LIVE; Official Review 85w + guide 468w, 4 data signals, self canonical |
| 43 | /p/national-wwii-museum-new-orleans | yes | 3 | Submitted and indexed | ALREADY LIVE; Official Review 79w + guide 374w, 4 data signals, self canonical |
| 44 | /p/new-orleans-museum-of-art | yes | 3 | Submitted and indexed | ALREADY LIVE; Official Review 88w + guide 372w, 5 data signals, self canonical |
| 45 | /p/pinacoteca-di-brera-milan | yes | 3 | Submitted and indexed | ALREADY LIVE; Official Review 81w + guide 379w, 4 data signals, self canonical |
| 46 | /p/sainte-chapelle-paris | yes | 3 | Submitted and indexed | ALREADY LIVE; Official Review 83w + guide 574w, 4 data signals, self canonical |
| 47 | /p/byzantine-and-christian-museum-athens | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 80w + guide 379w, 4 data signals, self canonical |
| 48 | /p/deutsches-museum-munich | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 86w + guide 423w, 4 data signals, self canonical |
| 49 | /p/epic-the-irish-emigration-museum-dublin | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 90w + guide 529w, 5 data signals, self canonical |
| 50 | /p/palace-of-holyroodhouse-edinburgh | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 97w + guide 556w, 4 data signals, self canonical |
| 51 | /p/rijksmuseum-amsterdam | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 505w, 4 data signals, self canonical |
| 52 | /p/rules-london | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 83w + guide 322w, 5 data signals, self canonical |
| 53 | /p/stedelijk-museum-amsterdam | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 87w + guide 475w, 5 data signals, self canonical |
| 54 | /p/ta-karamanlidika-tou-fani-athens | yes | 2 | Submitted and indexed | ALREADY LIVE; Official Review 91w + guide 335w, 5 data signals, self canonical |
| 55 | /p/copernicus-science-centre-warsaw | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 80w + guide 405w, 4 data signals, self canonical |
| 56 | /p/empire-state-building-new-york-city | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 90w + guide 557w, 5 data signals, self canonical |
| 57 | /p/gallerie-dell-accademia-venice | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 91w + guide 591w, 5 data signals, self canonical |
| 58 | /p/hotel-d-angleterre-copenhagen | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 81w + guide 388w, 4 data signals, self canonical |
| 59 | /p/pinakothek-der-moderne-munich | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 81w + guide 440w, 4 data signals, self canonical |
| 60 | /p/swiss-national-museum-zurich | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 76w + guide 364w, 4 data signals, self canonical |
| 61 | /p/versailles-restaurant-miami | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 80w + guide 409w, 5 data signals, self canonical |
| 62 | /p/whitney-museum-of-american-art-new-york-city | yes | 1 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 527w, 5 data signals, self canonical |
| 63 | /p/alte-pinakothek-munich | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 88w + guide 446w, 4 data signals, self canonical |
| 64 | /p/capitoline-museums-rome | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 85w + guide 396w, 4 data signals, self canonical |
| 65 | /p/gundel-budapest | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 93w + guide 321w, 5 data signals, self canonical |
| 66 | /p/hofbrauhaus-munich | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 94w + guide 326w, 5 data signals, self canonical |
| 67 | /p/hotel-sacher-vienna | yes | 0 | Submitted and indexed | ALREADY LIVE; Official Review 90w + guide 380w, 4 data signals, self canonical |
| 68 | /p/jewish-museum-berlin | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 89w + guide 479w, 5 data signals, self canonical |
| 69 | /p/joe-s-stone-crab-miami | yes | 0 | Discovered - currently not indexed | ALREADY LIVE; Official Review 88w + guide 367w, 4 data signals, self canonical |
| 70 | /p/katz-s-delicatessen-new-york-city | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 94w + guide 331w, 5 data signals, self canonical |
| 71 | /p/moderna-museet-stockholm | yes | 0 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 348w, 4 data signals, self canonical |
| 72 | /p/national-museum-of-iceland-reykjavik | yes | 0 | Submitted and indexed | ALREADY LIVE; Official Review 83w + guide 365w, 5 data signals, self canonical |
| 73 | /p/nationalmuseum-stockholm | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 82w + guide 372w, 5 data signals, self canonical |
| 74 | /p/nijo-castle-kyoto | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 80w + guide 564w, 4 data signals, self canonical |
| 75 | /p/oceanario-de-lisboa-lisbon | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 100w + guide 514w, 4 data signals, self canonical |
| 76 | /p/pasteis-de-belem-lisbon | yes | 0 | Submitted and indexed | ALREADY LIVE; Official Review 93w + guide 349w, 5 data signals, self canonical |
| 77 | /p/st-paul-s-cathedral-london | yes | 0 | Submitted and indexed | ALREADY LIVE; Official Review 84w + guide 542w, 3 data signals, self canonical (only 3 data signals: add hours/phone) |
| 78 | /p/tokyo-national-museum | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 87w + guide 392w, 3 data signals, self canonical (only 3 data signals: add hours/phone) |
| 79 | /p/vatican-museums-rome | yes | 0 | Submitted and indexed | ALREADY LIVE; Official Review 91w + guide 443w, 4 data signals, self canonical |
| 80 | /p/warsaw-rising-museum | yes | 0 | URL is unknown to Google | ALREADY LIVE; Official Review 83w + guide 388w, 5 data signals, self canonical |

## 5. Tier 2: include after a specific fix (4)

| # | URL | Live sitemap | GSC impr 16mo | Coverage | Reason |
|---|---|---|---|---|---|
| 1 | /p/museo-nazionale-romano-rome | yes | 3 | Submitted and indexed | ALREADY LIVE; keep, but verify "Crypta Balbi temporarily closed" against museonazionaleromano.beniculturali.it (may be stale) |
| 2 | /p/basilica-de-la-sagrada-familia-barcelona | yes | 0 | Submitted and indexed | ALREADY LIVE; keep, but verify "4.9 million visitors in 2025" and the "162 metres in October 2025" tower claim against sagradafamilia.org |
| 3 | /p/zur-letzten-instanz-berlin | no | 12 | Submitted and indexed | editorial present but no opening hours on page; add official hours (and confirm the 6/10 euro prices), then add to allowlist |
| 4 | /p/miod-malina-krakow | no | 0 | Submitted and indexed | editorial present, 5 signals; owner held it (reason not recorded): re-verify 48 zloty crostini price and Mon-Thu 13:00-21:30 hours on official site, then add |

## 6. Tier 3: keep out of the sitemap (98)

Template only pages (name, address, hours, map, nearby list). Under current policy they stay indexable for someone searching the name but are not submitted. The 6 chain branches (pizza-hut-las-vegas, pizza-hut-miami, starbucks-las-vegas, starbucks-lisbon, starbucks-miami, starbucks-new-york-city) are noindex candidates: a chain branch with boilerplate is the thinnest page type on the site. That needs a rule change in app/indexability.php and owner approval; not done.

Editorial queue if more Tier 1 pages are wanted later (highest GSC impressions among basics pages; British Museum is also a landmark): pizzeria-ai-marmi-rome (23), museu-de-arte-contemporanea-mac-ccb-lisbon (16), british-museum-london (11), indian-ymca-london (6), hanoi-ca-phe-opera-paris (5), harefield-road-new-york-city (5), aqui-ha-peixe-lisbon (4), charles-dickens-museum-london (4), court-street-new-york-city (4), groot-melkhuis-amsterdam (4), le-loir-dans-la-theiere-paris (4), swift-hibernian-lounge-new-york-city (4). Each needs a written, fact checked Official Review before it moves to the sitemap.

| # | URL | Live sitemap | GSC impr 16mo | Coverage | Reason |
|---|---|---|---|---|---|
| 1 | /p/pizzeria-ai-marmi-rome | no | 23 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 23 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 2 | /p/museu-de-arte-contemporanea-mac-ccb-lisbon | no | 16 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 16 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 3 | /p/british-museum-london | no | 11 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 11 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 4 | /p/indian-ymca-london | no | 6 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 6 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 5 | /p/hanoi-ca-phe-opera-paris | no | 5 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 5 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 6 | /p/harefield-road-new-york-city | no | 5 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 5 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 7 | /p/aqui-ha-peixe-lisbon | no | 4 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 4 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 8 | /p/charles-dickens-museum-london | no | 4 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 4 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 9 | /p/court-street-new-york-city | no | 4 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 4 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 10 | /p/groot-melkhuis-amsterdam | no | 4 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 4 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 11 | /p/le-loir-dans-la-theiere-paris | no | 4 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 4 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 12 | /p/swift-hibernian-lounge-new-york-city | no | 4 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 4 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 13 | /p/the-shops-at-columbus-circle-new-york-city | no | 4 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 4 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 14 | /p/chez-l-ami-jean-paris | no | 3 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 3 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 15 | /p/el-triangle-barcelona | no | 3 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 3 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 16 | /p/foam-amsterdam | no | 3 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 3 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 17 | /p/tico-tico-lisbon | no | 3 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 3 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 18 | /p/fraunces-tavern-museum-new-york-city | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 19 | /p/maison-europeenne-de-la-photographie-paris | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 20 | /p/marjorie-barrick-museum-of-art-and-harry-reid-center-las-vegas | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 21 | /p/mother-s-new-york-city | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 22 | /p/musee-grevin-paris | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 23 | /p/nam-kee-amsterdam | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 24 | /p/pastelaria-benard-lisbon | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 25 | /p/the-breakfast-club-amsterdam | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 26 | /p/turk-s-head-cafe-london | no | 2 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 2 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 27 | /p/a-tasca-do-chico-lisbon | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 28 | /p/all-bar-one-london | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 29 | /p/barcade-new-york-city | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 30 | /p/bembe-new-york-city | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 31 | /p/cafe-noir-amsterdam | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 32 | /p/cite-des-sciences-et-de-l-industrie-paris | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 33 | /p/espace-dali-paris | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 34 | /p/finnegan-s-road-miami | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 35 | /p/kush-wynwood-miami | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 36 | /p/le-cafe-marly-paris | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 37 | /p/les-deux-magots-paris | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 38 | /p/maison-de-victor-hugo-paris | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 39 | /p/manteigaria-fabrica-de-pasteis-de-nata-rambuteau-paris | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 40 | /p/origen-museum-las-vegas | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 41 | /p/public-house-las-vegas | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 42 | /p/sol-et-soleil-new-york-city | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 43 | /p/southside-coffee-new-york-city | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 44 | /p/the-mob-museum-las-vegas | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 45 | /p/theo-thijssen-museum-amsterdam | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 46 | /p/tick-tock-diner-new-york-city | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 47 | /p/tir-na-nog-new-york-city | no | 1 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 1 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 48 | /p/404-paris | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 49 | /p/al-peperoncino-rome | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 50 | /p/american-museum-of-natural-history-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 51 | /p/barrica-amsterdam | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 52 | /p/beere-mangiare-e-co-rome | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 53 | /p/best-bagel-and-coffee-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 54 | /p/bouillon-chartier-paris | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 55 | /p/cacio-e-pepe-rome | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 56 | /p/da-francesco-rome | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 57 | /p/denny-s-las-vegas | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 58 | /p/freemans-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 59 | /p/galleria-borghese-rome | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 60 | /p/giraffe-london | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 61 | /p/grails-miami | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 62 | /p/ice-club-rome | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 63 | /p/kremlin-lisbon | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 64 | /p/le-streghe-rome | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 65 | /p/lisb-on-hostel-lisbon | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 66 | /p/little-alley-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 67 | /p/lower-east-side-tenement-museum-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 68 | /p/maceo-paris | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 69 | /p/madame-tussauds-las-vegas | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 70 | /p/mikado-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 71 | /p/musee-d-orsay-paris | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 72 | /p/musee-des-arts-et-metiers-paris | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 73 | /p/museu-da-marioneta-lisbon | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 74 | /p/museu-nacional-do-traje-e-da-moda-lisbon | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 75 | /p/nemo-science-museum-amsterdam | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 76 | /p/nha-trang-one-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 77 | /p/palazzo-doria-pamphilj-rome | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 78 | /p/panther-coffee-miami | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 79 | /p/patricia-and-phillip-frost-museum-of-science-miami | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 80 | /p/pizza-hut-las-vegas | no | 0 | Submitted and indexed | chain branch with template text only: keep out; noindex candidate |
| 81 | /p/pizza-hut-miami | no | 0 | Submitted and indexed | chain branch with template text only: keep out; noindex candidate |
| 82 | /p/pizzaexpress-london | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 83 | /p/rivas-mexican-grill-las-vegas | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 84 | /p/starbucks-las-vegas | no | 0 | URL is unknown to Google | chain branch with template text only: keep out; noindex candidate |
| 85 | /p/starbucks-lisbon | no | 0 | URL is unknown to Google | chain branch with template text only: keep out; noindex candidate |
| 86 | /p/starbucks-miami | no | 0 | Submitted and indexed | chain branch with template text only: keep out; noindex candidate |
| 87 | /p/starbucks-new-york-city | no | 0 | Submitted and indexed | chain branch with template text only: keep out; noindex candidate |
| 88 | /p/sushi-sake-miami | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 89 | /p/t-bone-station-rome | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 90 | /p/the-corner-miami | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 91 | /p/the-good-fork-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 92 | /p/the-monkey-puzzle-london | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 93 | /p/toasties-new-york-city | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 94 | /p/venustempel-sexmuseum-amsterdam | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 95 | /p/vermeer-amsterdam | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 96 | /p/villa-vizcaya-miami | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 97 | /p/wereldmuseum-amsterdam | no | 0 | URL is unknown to Google | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |
| 98 | /p/willet-holthuysen-amsterdam | no | 0 | Submitted and indexed | template only (0 unique words, desc is boilerplate); 0 impr/16mo: keep out of sitemap, stays indexable per current policy |

## 7. Issues and risks

1. **The 264 figure double counts.** The 82 review pages are the 82 places already submitted. "Publish all 264" would in practice add the 98 basics only pages plus 2 held pages to a sitemap that already has the 82.
2. **98 basics pages are template content** (0 unique words, one shared description template, no reviews, no photos). Submitting them repeats the pre 2026-10-30 problem the allowlist was created to fix. 76 of them are already in Google's index from the old sitemap; leaving them out of the sitemap does not remove them, only noindex would.
3. **Self serving Review/Rating JSON-LD.** 26 of the 84 editorial pages emit a schema.org Review with reviewRating by RuinMyTrip Editorial on a Restaurant/LocalBusiness entity (the other 58 do not, so behaviour is inconsistent). Google treats first party reviews of a business as ineligible for review snippets, and at scale it can draw a structured data manual action. Suggest dropping the rating from editorial JSON-LD (code change, needs approval).
4. **12 live sitemap pages still unknown to Google** two to three days after submission (incl. Katz's, Hofbrauhaus, Tokyo National Museum). Normal crawl lag for a small site; recheck in about 2 weeks. Ekstedt is the only page with real recent demand (86 impressions in 28 days, position 6.8); Book of Kells has 188 impressions at position 68.
5. **Possibly stale facts on 2 live pages:** Sagrada Familia ("4.9 million people came in 2025", "162 metres in October 2025") and Museo Nazionale Romano ("Crypta Balbi temporarily closed"). Prices on the other pages were not re-verified in this audit.
6. **Catalog data quality on basics pages** (Tier 3, not submitted): pizzeria-ai-marmi-rome and finnegan-s-road-miami list m.facebook.com as the website; indian-ymca-london is typed "restaurant" (it is a hostel with a canteen); harefield-road-new-york-city shows postcode 11203 for 769 Metropolitan Avenue (that address is 11211).
7. **No photos anywhere** in the candidate set; og:image is a shared per city image. Photos are a signal in the rules but count 0 on every page.
8. 5 Tier 1 pages have only 3 data signals (no 7 day hours and/or phone): museo-nacional-de-antropologia-mexico-city, roscioli-rome, westminster-abbey-london, st-paul-s-cathedral-london, tokyo-national-museum. Fine to keep; adding hours would strengthen them.
