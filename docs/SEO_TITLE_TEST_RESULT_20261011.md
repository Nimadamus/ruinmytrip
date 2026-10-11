# Destination title test: result and reversal, 2026-10-11

## The test
- Change: commit 856c669, pushed 2026-09-15 20:57 PDT; main auto deploys, so live from 2026-09-16.
- Test group (9 city pages, new title and description): amsterdam-netherlands, lisbon-portugal,
  berlin-germany, marrakech-morocco, zanzibar-tanzania, hoi-an-vietnam, oaxaca-mexico, banff-canada,
  milan-italy.
- Control group: the other 76 city pages `/d/{slug}`, titles unchanged.
- Nothing else differed between the groups: same template, same sitemap, same internal links.

## Data
Search Console API, `page` x `date`, pages matching `^https://ruinmytrip.com/d/[^/?]+/?$`, pulled
2026-10-11 (latest day with data 2026-10-08), with the credentials scripts/gsc_report.py uses.

| Window (dates inclusive) | Test impressions | Test clicks | Test avg position | Control impressions | Control clicks | Control avg position | Whole site impressions |
|---|---|---|---|---|---|---|---|
| Baseline 28 days, 8/19 to 9/15 | 62 | 0 | 43.1 | 20 | 0 | 20.4 | 1,599 |
| 12 days before, 9/04 to 9/15 | 23 | 0 | 24.1 | 16 | 0 | 10.0 | 778 |
| 12 days of test, 9/16 to 9/27 | 7 | 0 | 5.9 | 12 | 0 | 15.0 | 1,329 |
| After the spam update cliff, 9/28 to 10/10 | 2 | 0 | 7.0 | 6 | 0 | 35.0 | 276 |

## Method
Difference in differences on impressions over two equal 12 day windows either side of the change,
stopping on 9/27 so the site wide drop of 9/28 (September 2026 spam update) does not count against
either group. Clicks cannot be compared: both groups had zero in every window.

- Test: 23 to 7, down 70%.
- Control: 16 to 12, down 25%.
- Whole site: 778 to 1,329, up 71%.

The test group fell while the control held and the site grew. Amsterdam carries most of it (15 to
2). The samples are tiny, so this is not proof the new titles hurt; it is enough to say they did not
help, which is the loss rule in docs/SEO_TITLE_TEST_20260915.md. The new titles also promise
"travelers going there", which the pages cannot back up today with no trips posted.

## The reversal
Branch `seo/title-test-revert-20261011`, one commit on top of main: `RMT_DEST_SOCIAL_TITLE_TEST`
becomes empty, so the nine pages return to the control title and description. The variants stay in
`RMT_DEST_SOCIAL_TITLE_TEST_ENDED` for the record.

- Changes: the `<title>` and meta description of 9 pages. No URL, canonical, robots, sitemap or link
  change.
- Undo: `git revert` of the one commit, or put the array back.
- Ship it alone, not in the same deploy as any other SEO change, so its effect can be read cleanly.
- Measure: the same table above for the 28 days after deploy, test group against control.
