# The destination title test, started 2026-09-15

## What is being tested

Every city page was titled like a guidebook and is a community underneath. Over the 28 days to
2026-09-15 the city pages drew 85 impressions and **zero clicks**, on queries about tourist taxes
and ticket prices, which is intent this site does not serve. The homepage has said "meet travelers
going where you are going" all along, so the site said one thing at the front door and another on
every page a search engine showed.

The question: does saying both things in the title, the travel guide AND the travelers, earn a
click where saying only one of them earned none?

## The change, and its limits

Nine city pages carry a new `<title>` and `<meta description>`. Seventy six do not, and they are
the control. Nothing else changed anywhere: **no URL, no sitemap entry, no canonical, no robots
rule, no new page and nothing submitted to anybody.** The change is `RMT_DEST_SOCIAL_TITLE_TEST`
in `app/seo.php`, read by `rmt_destination_page_title()` and `rmt_destination_page_description()`,
and `tests/dest_title_test_group_test.php` fails if the group grows, if the control drifts, if a
title stops naming its city, or if the constant is ever used near a canonical, a robots rule or the
sitemap.

## The group, and why each one

All nine had impressions and a **0.0% CTR**, so none has a click worth risking.

| Page | Impr | Clicks | CTR | Position | Why it is in |
|---|---|---|---|---|---|
| amsterdam-netherlands | 28 | 0 | 0% | 44.2 | most seen city page on the site, converts at zero |
| lisbon-portugal | 10 | 0 | 0% | 50.7 | second most seen, same |
| berlin-germany | 7 | 0 | 0% | 58.7 | real impressions, far from page one, nothing to lose |
| marrakech-morocco | 5 | 0 | 0% | 55.4 | same |
| zanzibar-tanzania | 5 | 0 | 0% | **4.4** | already on page one and still nobody clicks: the purest CTR case there is |
| hoi-an-vietnam | 3 | 0 | 0% | 53.0 | real impressions, nothing to lose |
| oaxaca-mexico | 3 | 0 | 0% | 73.7 | same |
| banff-canada | 2 | 0 | 0% | **5.5** | page one, zero clicks |
| milan-italy | 2 | 0 | 0% | **5.0** | page one, zero clicks |

Three of the nine already sit on page one and convert at nothing, which is where a title is the
only variable left. The other six are far enough down that a title cannot make them worse in any
way that matters.

## Baseline, frozen

`docs/seo_baseline_20260915.json`, pulled 2026-09-15 Pacific, 28 day window.

* Test group: 9 pages, **65 impressions, 0 clicks**
* Control, destination pages with impressions: 18 pages, **20 impressions, 0 clicks**
* Whole site: **1,372 impressions, 0 clicks**, average position 46.5

## How to read the result, and when

Google needs to recrawl and re-serve these pages before the titles even appear in results, so
**nothing before 14 days means anything**, and 28 days is the first honest read.

    python scripts/gsc_report.py --days 28 --json after.json

Then compare, per page and in total:

1. **CTR** on the nine against their own baseline. This is the number the test is about.
2. **Clicks.** Any click at all is more than the baseline, which is zero across every page.
3. **Impressions and position.** These are the guard rails rather than the result: a title change
   can cost relevance for the tax and ticket queries these pages currently appear for. A fall in
   impressions on the nine while the control holds steady is the failure mode to watch for.
4. **The control group** over the same window, to separate a real effect from a site wide drift.

### What counts as which outcome

* **Win:** any clicks on the nine, or CTR above zero, with impressions not materially down.
  Then roll the wording out to the rest, in batches, with the same comparison each time.
* **Neutral:** still zero clicks and impressions unchanged. Then the title was never the binding
  constraint and the answer is traffic, not wording. Keep or revert on taste, and stop testing
  titles.
* **Loss:** impressions on the nine fall while the control holds. Revert immediately: the change
  is one constant in one file.

### The caveat that matters most

65 impressions over 28 days cannot produce a statistically significant CTR result. At these volumes
the honest reading is directional only: a first click is evidence, a 0.5% difference is noise. The
real fix for that is traffic, and this test does not provide it.
