# Production deploy log, 2026-10-11 (afternoon, owner approved steps 1 to 4)

All times Pacific. Every step: SEO snapshot of 26 URLs before and after (`prod_check.py`), counts
through the app before and after (`dbsnap.py`), Render logs (Fatal, PHP Warning, 500), memory,
desktop and phone smoke test with a refused review and a refused sign up (`prod_smoke.py`).

| # | Live at | Deploy | Commit | Change | Result |
|---|---|---|---|---|---|
| 1 | 11:00 | dep-db5st1rbc2fs73a0uqb0 | cde078c (same code) | CRON_KEY rotated (fingerprint 85f3 to ec0c); CREDENTIALS.md updated before the restart | new key 200, old key 404; both scheduled jobs run by hand 11:00, exit 0, their /cron calls 200; no key in any URL; SEO invariants 0 changes |
| 2 | 11:06 | dep-db5svvqvcj2c7395tpm0 | 869d6ec | Held review counted as published once | gate 138 and E2E 68 at this exact commit; counts unchanged (only new visits); SEO 0 changes; logs clean |
| 3 | 11:14 | dep-db5t3m2jnfac73bl5k2g | 4d71ab9 | Title test reversal, alone | exactly the 9 test cities changed title and description; canonical, robots, H1, og:url unchanged; controls unchanged; Search Console baseline recorded first |
| 4 | 11:24 | dep-db5t8inlot8c73845l50 | 04b8e01 | Redesign merged, every switch off | 0 SEO changes, no v2 markup on public pages; previews noindex with the real canonical; cookie preview never leaks to cookieless visitors; protected pages still redirect or 403 |
| 4b | 11:35 | dep-db5tdl7avr4c73a0vvgg | 2f678af | Redesign polish from the production preview (switches still off) | 0 SEO changes; smoke clean live and preview |
| 4c | 12:02 | | c62064e | Redesign polish 2 (switches still off) | 0 SEO changes |
| 5 | 17:08 | dep-db629f2d0e5s7394lvog | 1f3e32f | Campaign measurement: per channel reviews, map shares, returning visitors, suspected bots; place page action row fix | gate 140; SEO 0 changes |
| 5b | 17:15 | dep-db62cp59fdbs73eid1o0 | ebe49a0 | Per channel people = the /admin/proof verdict | channel rows add up (people + bots + unverified = sessions) |
| 5c | 17:21 | dep-db62fkd9fdbs73eimbl0 | f8df6cd | Verdicts read over the report window only (an all time read held 230MB) | /cron/funnel memory back to 177MB peak; selfcheck through a tracked invite link counted 1 session, 1 map view, 1 map share, kept apart |
| 6 | 17:26 | dep-db62i37lot8c738kv2u0 | f8df6cd | RMT_PLACE_V2=1 on Render: redesigned place pages public (nothing else) | 44 place pages: title, description, canonical, robots, JSON-LD, H1 all unchanged; 26 page invariants 0 changes; desktop and phone audit clean; refused review and sign up paths fine; logs clean. Rollback: set RMT_PLACE_V2=0 and deploy (about 3 minutes) |
| 7 | 17:47 | dep-db62rn3bc2fs73ajun0g | 532b9c8 | Redesign polish 3 (other surfaces still off) | SEO 0 changes; preview audit clean |

Cron key holders, scanned before rotating: Render env CRON_KEY (this service only; no other Render
service holds it) and ~/CREDENTIALS.md. No Windows environment variable, no other file in tools,
repos or task folders, no GitHub workflow.

Still off: redesign surfaces other than place (home, nav, explore, city, profile), the curated sitemap. Campaign 1 assets ready, nothing posted.

| # | PDT | Deploy | Commit | Change | Verified |
|---|---|---|---|---|---|
| 8 | 19:34 | dep-db64dptckfvc73c3fed0 | f0c0ed2 | Live profile fix: talk excerpts no longer double every line break (pre-wrap under nl2br) | 5 profiles: title, description, canonical, robots, JSON-LD, H1 and full page text unchanged; /u/ruinmytrip talk cards 40% shorter on desktop (2074 to 1254 px), 24% on phone; no horizontal scroll, no errors |
| 9 | 19:39 | dep-db64g9rbc2fs73ap9310 | e284983 | Home redesign preview: the travel map is the hero; profile ellipsis tidy | public pages unchanged |
| 10 | 19:48 to 20:02 | d603cd1, 0026532, 5e2f0cc, 9240274 | | Home preview: whole map card above the fold, every city and travelers link kept, small image copies (2.3 MB to 0.44 MB on phones), chips swipe on phones | home SEO head identical public vs preview; 26 page invariants 0 changes; 44 place pages 0 changes; sitemap index 4 |

Still off: home, nav, explore, city and profile redesign; the curated sitemap. Nothing posted or sent.
| 11 | 20:14 to 20:22 | 3b02756, 18b209c, bbd43b4 | | Home preview v3: community pillars under the map, "Travelers are asking", nine city tiles, quiet join line, footer columns, phone swipe rows | home SEO head identical public vs preview; preview phone LCP 1.08 s vs public 1.15 s |
| 12 | 20:46 to 21:25 | 1eaeb9d, bbc, 4169e55 | | Home preview: five purposes under the map; community stream restored (every review and question link the current home has); measurement: first map tap, shares, downloads, saves and first contributions per channel; accessibility fixes | home links: 0 missing vs public; 36 page loads desktop and phone, public and preview: 0 errors, 0 overflow; 426 internal links all 200; axe serious or critical on preview: 0; refused review and signup paths pass; selfcheck counted 1 session, 1 tap, 1 share, 1 download, kept apart |
