# RuinMyTrip: 30 day organic growth plan (2026-11-09)

Read only research and staged work. Nothing deployed. Technical SEO (redirects, canonical, robots,
noindex, sitemap) frozen on owner's order; nothing below touches it.

## Baseline (pulled 2026-11-09)

| Measure | Value | Source |
|---|---|---|
| Google clicks, 28 days (10/10 to 11/06) | 9 | GSC, https://ruinmytrip.com/ |
| Google impressions, same period | 946, average position 14.7 | GSC |
| Verified human visitors, 31 days | 64 (34 from search, 16 referral, 5 returning) | /cron/proof?days=31 |
| Review form opened, 31 days | 5 (10/10, 10/30, 11/02, 11/03, 11/05) | /cron/proof |
| Pressed Post on a review, 31 days | 0 | /cron/proof |
| Reviews by genuine members, ever | 0 | daily report 11/08 |

Pages with the most impressions (28 days): /p/esperit-sants-barcelona 150 impressions, position 9.6,
1 click (queries "esperit sants barcelona", "esperit sants", "esperit sants restaurant");
/blog/san-francisco-hotel-tax-2026 89, position 6.6, 0 clicks; /travel-buddies/greece 43, position 10;
/blog/gold-museum-san-jose-tickets-2026 23, position 4.7, 0 clicks.

## What the numbers say

1. **The review form is not the bottleneck.** 5 opens in a month, 0 presses of Post. The form is
   already guest friendly (write first, join after, drafts held, prefilled from place pages). With 5
   opens a month, even a perfect form yields one or two reviews. There is no field level data to say
   where those 5 stopped, and adding it is a production change, so it is not proposed now.
2. **Traffic is the bottleneck.** 64 humans a month cannot make a community site look alive.
3. **Search is the only channel that works without a person posting by hand**, and it is small and
   slow. Judge it over 4 to 8 weeks, not days.

## Priorities (each item staged on a branch, shown to the owner, deployed only on his yes)

| Week | Action | Why | Measure (judge at day 30, not before) |
|---|---|---|---|
| 1 | **Done, staged:** sourced scam sections for Budapest (5 items) and Tokyo (2), branch `content-1109` | Same proven format as the 24 live cities; official quotes verified verbatim 11/09 | Impressions on /d/budapest-hungary and /d/tokyo-japan |
| 1 | **Dropped 11/09:** /p/esperit-sants-barcelona. No official website or menu found; the editorial rule is official sources or no facts, so the page stays as is | | |
| 2 | **Left alone 11/09:** /blog/san-francisco-hotel-tax-2026. Keyword research of 11/07 marks it the best ranker, "leave the content alone"; it already states 14 percent TOT plus TID | Protect what works | |
| 2 | **Staged 11/09:** sourced scam sections for Medellin (3 items) and Havana (3), branch `content-1109`; Canada names Medellin, FCDO names Old Havana | Same rule as above | Impressions on both city pages |
| 1 to 2 | Amsterdam council votes the 2027 budget and tourist tax rate 11/11 to 11/12 (proposal 16 percent). Update /blog/amsterdam-tourist-tax-2026 in place once amsterdam.nl publishes the decided rate | Queries for "amsterdam tourist tax 2027" | Clicks on that post |
| 2 | More sourced scam sections only where FCDO, US State or Canada name the city (checked 11/09 across 27 countries: only Budapest, Tokyo, Medellin and Havana qualified; no padding) | Section rule: city specific official advice or nothing | Count of cities, impressions |
| 3 | Answer bank use (docs/ANSWER_BANK_20261107.md): owner posts from his own accounts, at most 3 a day, disclosed | Referral was the second channel (16 humans) | Tagged link visits per thread |
| 4 | Review the numbers; keep what moved, drop what did not | | 28 day GSC vs this baseline |

## Not doing

- No technical SEO change, no new templated city pages, no review form instrumentation in production.
- No Facebook group posts: no personal Facebook account exists for this work (docs/ACQUISITION_FACEBOOK.md)
  and personal messages are off (owner 11/02).
- No fake or house written reviews presented as members.

## Already built before this plan (checked 11/09)

Keyword research items 3 (2027 rates: Barcelona already states the April 2027 surcharge; Amsterdam awaits the vote) and 14 ("worth it / skip if": editorial /p/ pages already carry best_for and skip_if) need no new work.
