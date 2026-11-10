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
| 1 | Improve /p/esperit-sants-barcelona content: what is verifiably on the page today vs what searchers want (menu prices, hours, how to book) from the restaurant's own site | 150 impressions at position ~10: the page Google already tests most | Clicks and position on its 3 queries |
| 2 | Improve /blog/san-francisco-hotel-tax-2026 and /blog/gold-museum-san-jose-tickets-2026 body content against the official sources (SF Treasurer, Banco Central de Costa Rica museums) | Shown at positions 4.7 to 6.6 with 0 clicks: the content must answer the search better | Clicks; no title or meta change without owner OK |
| 2 | More sourced scam sections only where FCDO, US State or Canada name the city (checked 11/09: Naples, Venice, Berlin, Reykjavik, Zanzibar did not qualify; no padding) | Section rule: city specific official advice or nothing | Count of cities, impressions |
| 3 | Answer bank use (docs/ANSWER_BANK_20261107.md): owner posts from his own accounts, at most 3 a day, disclosed | Referral was the second channel (16 humans) | Tagged link visits per thread |
| 4 | Review the numbers; keep what moved, drop what did not | | 28 day GSC vs this baseline |

## Not doing

- No technical SEO change, no new templated city pages, no review form instrumentation in production.
- No Facebook group posts: no personal Facebook account exists for this work (docs/ACQUISITION_FACEBOOK.md)
  and personal messages are off (owner 11/02).
- No fake or house written reviews presented as members.
