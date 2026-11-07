# RuinMyTrip launch plan and audit, 2026-11-07

Branch: `claude/relaxed-wright-enrc9x`, **not deployed**. Only pushes to `main` deploy (render-deploy.yml).
Research behind this file: `docs/research_20261107/` (KEYWORDS, TECH_SEO, MONETIZATION, CLAIMS_FACTS).

## 1. The one-paragraph diagnosis

The product is far ahead of its audience. It has an unusually complete, well-tested social travel
product: reviews, warnings, a buddy finder, hosts and locals, meetups, messaging gated on mutual consent,
168 test suites and an adversarial safety suite. What it lacks is people and search traffic. Search
Console showed about 1,900 impressions and 9 clicks over 28 days in October, after the September 2026
spam update cut impressions by roughly 80%. The buddy pages show labelled sample listings, and every
"what ruined it" line on the live site was written by the site team. The way to A+ is not more
features or more pages. It is (a) content only this site can credibly publish, (b) fixing the signals
that look scaled, and (c) getting the first real travelers to contribute.

## 2. Grades (evidence-based)

| Area | Grade | Evidence | What blocks A+ |
|---|---|---|---|
| Engineering and reliability | A- | 168 test suites; 149/149 browser journey checks after fixes (phone and desktop); 0 broken links in 734 internal targets | Five suites only run against a locally seeded DB, so a fresh checkout reads red ([§6](#6-operational-notes)) |
| Safety, privacy, moderation | A- | Destination-level location only; messaging needs mutual accept; 18+ gates; report/block everywhere; `safety_test`, `adversarial_test` | Free overnight stays are live without the legal review and moderator coverage `docs/HOSTS_LOCALS_20261031.md` lists as prerequisites |
| Legal readiness | D | Terms say "plain-language summary for the MVP and will be expanded before public launch"; no hosting/stays clause, no operator identity, governing law or notice-and-takedown | Lawyer-reviewed Terms covering stays, references and liability |
| Homepage and visual design | B+ | Modern v2 design, clear hero, honest labelling, fast | The community band is mostly the team's own questions (labelled); the title promised something the page did not show (fixed on branch) |
| Mobile | A- | No horizontal overflow at 390px on any page tested; bottom tab bar | Feed icon buttons were 24px and Sign out 23px (fixed on branch) |
| Navigation and discovery | B- | Clear primary nav; search finds cities and places | /blog and /guides are not linked from home, nav or footer; blog posts get a median of 2 inbound links |
| Content quality and trust | B | Editorial is always labelled and never counted in ratings | /ruined claimed lines were "from people who were there" while all 90 were editorial (fixed on branch); 133 templated "budget" posts form a scaled-looking footprint |
| Technical SEO | B- | All 409 sitemap URLs: 200, self-canonical, valid JSON-LD (crawl) | Trailing-slash duplicates; about 372 indexable URLs left out of every sitemap; templated titles (63 guide titles end in a literal "…"); thin internal linking |
| Organic traffic | F | 9 clicks / 28 days | Everything above, plus time |
| Community activity | D | Example listings on /buddies; no member warnings on /ruined | Real first contributors (see §5) |
| Performance | A- | Warm TTFB 250–600 ms, LCP 0.6–1.8 s | Free Render instance cold starts; nothing cached at Cloudflare; blog hero served full size (fixed on branch) |
| Monetization | not started (by design) | Only a disclosure page exists | Traffic; a decision on which programs fit (§7) |
| **Launch readiness overall** | **C+** | | Legal terms for stays, real contributors, the SEO decisions in §4 |

## 3. What changed on the branch (all tested, none deployed)

| Commit | What | Verified by |
|---|---|---|
| 262e3b8 | /ruined shows "From travelers" and "From our research" separately with honest counts and an empty state that asks for the first warning. Feed has an h1. 44px tap targets. `/review/new?place=<slug>` works. Journey script added | `ruined_test` (11), `pages_render_test`, 149/149 journeys |
| 9598327 | Homepage title and description match the hero (reviews, warnings, buddies); Organization schema. **Ship alone** so its effect can be read | `meta_test`, `seo_test` |
| 4918b14 | Legal pages show the month their text last changed, not today's | manual |
| 57c7654, 4b60345, a4116f7 | "Scams and traps" on 8 city pages: 42 items from UK FCDO, Government of Canada, the Paris police, Barcelona City Council and airport operators, each with source, URL and quote. Linked from the homepage, /ruined and back. A section, not new URLs | `city_scams_test` (214 checks); 3 quotes re-checked by hand |
| 67f408f | Guide/blog Article JSON-LD: ISO dates, author, image | `seo_test`, rendered output |
| 9813c44 | Blog hero srcset: phones load the 480px rendition; CLS 0 | Playwright measurement |
| abf1c53, 5a36c41 | One page: "Trip went wrong? What you can claim" (EU261, UK, Canada, US refunds, Montreal 1,519 SDR, package travel, FCBA), every figure linked to a regulator. Scam data moved out of `database/editorial` (it would have made the deploy-time publisher report errors) | `editorial_test`, publisher `--check`, link check |

## 4. Decisions needed from Nima

1. **Deploy this branch?** Recommended order: first everything except 9598327 (the homepage title), then
   9598327 on its own a week later so it can be measured. Or merge all of it, accepting that the title
   change cannot be read in isolation.
2. **Trailing-slash 301** (`docs/proposals/trailing_slash_301.patch`, prototyped and reverted). It sends any
   GET/HEAD for `/x/` to `/x`. Hardened so `//evil.com/` cannot become an off-site redirect. It changes
   canonical and redirect behavior, so it needs your approval. Recommended.
3. **The ~372 indexable but unsubmitted URLs** (TECH_SEO §2). For each group, either noindex it or submit it.
   Recommendation: noindex the 195 `/review/{id}` editorial mirror pages, whose content is already on the
   place page and whose titles are cut off; keep `/d/{city}/places` indexable but unsubmitted; leave
   `/in/{country}` as it is. Nothing done.
4. **Add `/ruined` to sitemap-core.** Recommended only once it carries traveler warnings. Today it is
   honest but mostly research.
5. **Guide titles cut with a literal "…"** (63 pages, `app/seo.php:455`). This is title churn across many
   pages, which the September test warns against. Recommendation: fix in one batch with a control group.
   Not done.
6. **Editorial star ratings in Review markup on 24 place pages.** The page says "not from a personal trip"
   while the markup carries a rating. Recommendation: drop `reviewRating` from editorial Review markup.
   Not done (structured-data policy change).
7. **Overnight stays.** They are live. The hosting doc's own prerequisites (legal review, moderator
   coverage) are open. Recommendation: either get the Terms reviewed or pause stays
   (`rmt_stays_enabled()`) until they are. Your call; nothing changed.
8. **Monetization, first application:** AirHelp referral on the claims page only (no minimums; 15% of
   AirHelp's fee per their page). It needs your sign-up and account. Nothing was applied for.

## 5. Strategy: SEO, content and community

**What to build** (from KEYWORDS.md; the site is new and was hit by the spam update):

1. **Tourist tax, the strongest asset.** The site already sits at positions 7–10 for Milan and San
   Francisco. Refresh the ranking posts in place (official source, check date, worked cost, calculator
   link). Add 2027 sections to existing URLs, never new ones.
2. **Scams and traps:** done for 8 cities. Grow it only where official sources name specific scams. Never
   a page per city.
3. **The claims page:** done. Measure it before writing anything similar.
4. **"Worth it if / skip if" blocks** on the ~80 editorial place pages (Louvre, Eiffel, Book of Kells),
   from facts already verified for those pages.
5. **Buddy and cruise pages:** their value depends on live trips. Keep the empty states honest.

**Do not build:** programmatic "[city] scams" or "travel buddy [city]" pages, duplicate 2027 tax URLs,
more templated ticket posts, "worst hotels/airlines" rankings without the site's own data, per-country
safety pages, per-airline claim pages, or house-written stories presented as traveler stories.

**Community cold start** (the real launch blocker). The machinery exists. What is missing is the first
50 contributors:
- The outreach kits in `docs/OUTREACH_*` and `docs/LAUNCH_POSTS_20261103.md` all wait on accounts only
  Nima can create or sign into.
- The one-line "what ruined it" ask is the lowest-friction contribution on the site. Lead with it in
  outreach, now that /ruined says honestly that it is empty.
- Measure through `/admin/funnel`; the measure that matters is distinct travelers who post.

## 6. Operational notes

- The gate needs `app/config.php` (gitignored) pointing at `database/dev.sqlite`, built with
  `php scripts/dev_db.php`, `php scripts/dev_fixtures.php` and the editorial publish. Without it, five
  suites skip or fail. `place_v2_test` (2) and `sitemap_articles_test` (2) fail on a fresh fixture DB
  identically on `main`: fixture data, not code.
- City pages are page-cached for 60 s signed out. Use `RMT_PAGE_CACHE=0` when checking changes locally.
- Production runs `RMT_DESIGN_V2=all`. Local checks must set it, or you are testing the old design.

## 7. Monetization (from MONETIZATION.md)

Order of fit: (1) AirHelp on the claims page; (2) Viator/GetYourGuide/Tiqets on ticket-price posts only;
(3) eSIM on practical posts. Hold off on hotel affiliates: they would sit next to reviews, which is the
biggest risk to "nobody can buy a rating". Hold off on display ads (no traffic) and sponsored placements
(no buyers, and the highest trust risk).

Rules: a "Paid link" note right next to each partner block, `rel="sponsored"`, never on review cards or
user content (`content_quality.php` already blocks affiliate links in posts; keep it), never sort by
commission. No revenue projections are made because none can be supported.
