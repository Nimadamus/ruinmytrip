# Search snippet test (branch feat/meta-test-20261018, NOT deployed)

Only the meta description of three pages changes (database/meta_tests.json, read by rmt_meta_test()).
Titles, URLs, canonicals, robots, sitemaps and body text stay as they are. The live descriptions stay
untouched until Nima approves.

## Baseline (recorded 2026-10-18, Search Console 2026-09-19 to 2026-10-16, Pacific days)
Raw numbers: docs/seo_snippet_baseline_20261018.json.

| Page | Impressions | Clicks | CTR | Avg position |
|---|---|---|---|---|
| /p/le-rapido-paris | 86 | 0 | 0 | 9.5 |
| /blog/milan-tourist-tax-2026 | 77 | 0 | 0 | 9.5 |
| /blog/tokyo-skytree-tickets-2026 | 19 | 0 | 0 | 5.4 |
| control: 4 blog posts, descriptions unchanged | 65 | 0 | 0 | 5.7 to 7.4 |

## Descriptions
- /p/le-rapido-paris
  - before: Le Rapido is a cafe in Paris. 25 Avenue Ledru-Rollin, Paris Ile-de-France 75012, France. See it on a map, save it to a trip, and read what travelers say.
  - after: Le Rapido is a cafe at 25 Avenue Ledru-Rollin in Paris 12e. Check today's opening hours, get directions or call, and read what travelers say about it.
- /blog/milan-tourist-tax-2026
  - before: Milan charges 12.00 euro per person per night in five star hotels from 1 April 2026 and 9.50 euro in short term rentals, per the Comune di Milano tourist tax page.
  - after: Milan city tax 2026 per person per night: 3 euro in one star hotels up to 12 euro in five star, 9.50 euro in rentals, from 1 April. Worked costs inside.
- /blog/tokyo-skytree-tickets-2026
  - before: Tokyo Skytree Tembo Deck tickets start at 1,800 yen for an adult online in 2026, with a 500 yen per ticket service fee added at the fourth floor counter.
  - after: Tokyo Skytree 2026: Tembo Deck from 1,800 yen online, both decks from 3,000 yen, children 6 to 14 from 900 yen. At the counter you pay 500 yen more.

Deployed 2026-10-18 8:23 PM PDT (cc82eb4, start date in meta_tests.json). Baseline is the saved 2026-09-19 to
2026-10-16 window; Search Console had no later days yet. Measure days 2026-10-26 to 2026-11-22 on 2026-11-25.

## Four week procedure
1. Day 0 (deploy day, after Nima's go): re-run `python scripts/gsc_snippet_test.py <D-28> <D-1>` so the
   baseline ends the day before the change; set "start" in meta_tests.json to day 0. Confirm live that
   only the three descriptions changed (prod_check snap/diff: le-rapido-paris and milan-tourist-tax are
   in its fixed set).
2. Google rewrites snippets on recrawl; days 1 to 7 are the changeover and are not counted.
3. Day 31 (Search Console runs 2 to 3 days behind): run `gsc_snippet_test.py <day 1> <day 28>`.
4. Read: clicks and CTR per page against the baseline, positions within about 1 place, and the control
   group for a seasonal swing. With 0 clicks at baseline any result is small; report counts, not
   percentages.
5. Keep a description only if its page gained clicks without losing position; otherwise remove its
   entry from meta_tests.json (rollback is that one line, no other change).
