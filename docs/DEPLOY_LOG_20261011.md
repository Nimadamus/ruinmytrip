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

Cron key holders, scanned before rotating: Render env CRON_KEY (this service only; no other Render
service holds it) and ~/CREDENTIALS.md. No Windows environment variable, no other file in tools,
repos or task folders, no GitHub workflow.

Not done (needs separate approval): public redesign switches, the curated sitemap, any campaign.
