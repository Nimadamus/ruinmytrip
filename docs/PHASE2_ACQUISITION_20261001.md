# Phase 2: acquiring the first real travelers (2026-10-01)

Shipped on main (commit 1367fb3), deployed `dep-dav84j7a7nis73c0nqk0`, live 08:55 PDT. Migration 105 is in production.

## 1. What is live

### Signed-out match alerts (`app/match_alerts.php`)

**Where the form appears:** on the three ecosystem pages, every city hub `/d/{city}`, every travelers page and every "I'm going" card. Members never see it; they get "Add my trip", because their trips already alert them.

**What it asks for:** email, arrival date, departure date and flexibility (exact, 3 days either way, or a week either way). The city and the event come from the page.

**Spam controls:**
- Double opt-in. Nothing is sent before the confirm click except the confirmation email itself.
- The confirm link is a one-button page, so a mail scanner that opens the link cannot switch the alert on.
- Only the sha256 of the confirmation token is stored.
- A hidden honeypot field. A bot that fills it is told the alert was saved, but nothing is stored.
- CSRF protection.
- Rate limits: 6 alerts per hour per IP address, and 4 per day per email address.
- One alert per email address per city. Asking again updates the dates.
- The form never reveals whether an address is already known.
- At most one email per alert per 20 hours, and at most 8 in an alert's lifetime.
- A signed one-click off switch in every email.
- Nobody is ever identified in an alert email.

**What triggers an email:** only a real overlap. That means a public trip or open buddy post by a real member, or another confirmed alert, inside the alert's window widened by its flexibility. House accounts never count.

**Conversion to an account:**
1. When an alert is submitted, the trip is held in the session and the email address is kept for the join form.
2. "Post my trip" opens `/plan` with the city and dates already filled in.
3. Once the address belongs to a confirmed account, by email confirmation or Google, every alert for that address becomes:
   - a public trip on those dates, unless the member already has a trip there on overlapping days;
   - a follow of the city;
   - status `converted`.

   The form states this in advance.
4. This was tested end to end both ways: in the same session, and with the account created in a different session.

### "I'm going" cards (`app/going_cards.php`)

- **Where they are made:**
  - members: from the new-trip landing (`/matches?new=`), or from an ecosystem page when they have a trip in its window;
  - signed-out visitors: from their just-set alert.
- **The page** (`/im-going/{code}`) shows:
  - the city photo;
  - "I'm going to Oaxaca for Day of the Dead, 31 October to 2 November 2026. Who else is going?";
  - the member's name and photo only if they ticked the box;
  - two buttons, I'm going too and Find travelers;
  - share buttons for the phone's share sheet, WhatsApp, Facebook, X, Telegram, email and copy link.

  A visitor without an account gets the alert form with the same dates already filled in.
- **The 1200x630 image:** `/card/going/{code}.png`.
- **Indexing:** card pages are noindex, because each one is a single person's dates.
- **Share tagging:** every share link carries `utm_medium=share&utm_campaign=im-going&utm_source={channel}`. Alerts, signups and trips that follow are attributed to the card without storing who the visitor is.
- **Visit counting:** each browser is counted once per card, and crawlers are never counted.

### The three ecosystem pages

| Page | Date we publish | Facts we deliberately do not publish |
|---|---|---|
| [/e/yi-peng-chiang-mai](https://ruinmytrip.com/e/yi-peng-chiang-mai) | Full moon Tuesday 24 November 2026 | No official TAT or municipal 2026 program yet, 2026 lantern zones, 2026 airport restrictions (the 2025 rules are given and labelled as 2025) |
| [/e/day-of-the-dead-oaxaca](https://ruinmytrip.com/e/day-of-the-dead-oaxaca) | Vigils on the nights of 31 October and 1 November; 1 and 2 November are the days themselves | The official 2026 program, cemetery prices |
| [/e/web-summit-lisbon](https://ruinmytrip.com/e/web-summit-lisbon) | 9 to 12 November 2026, MEO Arena | Night Summit venues and times |

- **What each page contains:**
  - a guide of 660 to 760 words;
  - "What travelers wish they knew" warnings;
  - FAQs, with FAQPage and Event markup;
  - the matching window;
  - the alert form and the action tiles;
  - "Who is going", read live;
  - city questions;
  - internal links to the city hub, its travelers page, the buddies page, /events and the other events;
  - a sourced fact card with 5 to 9 sources.
- **Fact checking:** every fact was checked on 2026-10-01 (`docs/ECOSYSTEM_FACTS_20261001.md`). The correction found was that 23 to 26 November for Yi Peng is not verified, so only the full moon date is published, and the /events window was aligned to match.
- **Quality bar** (`rmt_occasion_quality`): a page can be indexed with no members if it has at least 600 words, at least 4 guide sections, at least 3 warnings and at least 3 sources. All three pass. Bangkok New Year and Rio do not, so they stay noindex and out of the sitemap.
- **Sitemap:** the three pages are now in it, and it was resubmitted in Search Console.
- **URLs:** slugs no longer contain a year, following the standing no-dates-in-URLs rule. The old dated slugs 301 to the new ones. They had been live for less than a day and were noindex.

### Recognitions (`app/recognitions.php`)

- Founding Traveler already existed:
  - the rule: the first 100 accounts that publish a review;
  - it has a public promise page at `/founding`;
  - it is left unchanged, so the site never has two different "Founding" rules.
- Added: **First traveler to {city}** and **First review of {city}**:
  - **How it is earned:** the first real member to post public dates, or a published review, for that city. Real means active, not a house or editorial account, and email confirmed.
  - **The hold:** the trip or review must have been live for 48 hours.
  - **Display:** one small line under the name on a profile, at most 3 items, then "and N more". It is not shown on cards or lists.
  - **Permanence:**
    - Deleting a later trip does not take it away.
    - Moderation removal of the qualifying trip or review revokes it. It is not reassigned automatically.
  - **Anti-abuse:**
    - A unique index allows one First per city per kind.
    - The 48-hour hold means posting and deleting does nothing.
    - It is never awarded to house accounts.
    - It is never awarded for logins, clicks, follows or invites.
- **Deliberately not built:** an "Early Contributor" badge. It would duplicate Founding Traveler and add badge noise.

### Scoreboard

`/cron/funnel` now returns a `phase` block. It is one JSON object per window (`days=7`, `14`, `30`), with:
- landing visits
- alert submissions, confirmations, overlap emails and conversions
- registrations
- trips created
- cards created
- share clicks by channel
- shared-link visits
- signups from cards
- destination follows
- questions, reviews and posts
- traveler overlaps (pairs of real public trips crossing in a city)
- return visitors
- per-ecosystem-city counts

Organic impressions and clicks come from Search Console (script below).

## 2. Baseline before launch (2026-10-01)

**Search Console.** "URLs containing" counts include venue pages in that city.

| Window | Site impressions / clicks | Oaxaca URLs | Chiang Mai URLs | Lisbon URLs | /e/ pages |
|---|---|---|---|---|---|
| 28 days to 29 Sep | 2,360 / 6 | 2 / 0 | 21 / 0 | 108 / 2 | 0 / 0 |
| 90 days to 29 Sep | 3,151 / 6 | 14 / 0 | 38 / 0 | 136 / 2 | 0 / 0 |

**Product, all time:**
- 0 registrations
- 0 trips
- 0 follows
- 0 contributions
- 0 social interactions
- 0 return visitors
- 0 alerts
- 0 cards

Landing "visitors" over 7 days were 6,814 distinct browsers. That count is mostly scripted traffic, which is why the human count lives elsewhere.

City page visits over the last 7 days, from `phase.ecosystems`: Oaxaca 15, Chiang Mai 20, Lisbon 15. Alerts, trips, follows, cards and questions are all 0.

**Compare at 7, 14 and 30 days** (Oct 8, Oct 15, Oct 31) using the `phase` block, plus Search Console for the four rows above.

## 3. First 50 real travelers: the plan

Rule for every channel: post only where the rules allow it, add something useful first, disclose that we built the site, never message strangers in bulk, never invent a trip. Full rule quotes: `docs/CHANNELS_RESEARCH_20261001.md`.

**Realistic target:** 50 real people who post a real trip or set a confirmed alert, concentrated in the three cities, by 30 November. The overlaps that matter are 2 or more real trips in the same window in the same city.

| # | Channel | Why it can work | Exact action | Owner | Expected |
|---|---|---|---|---|---|
| 1 | Web Summit Side Events Guide (sideevents.guide/web-summit/2026: "Submit it for free"), Luma, Eventbrite | People there have fixed dates and are alone in a city | List a real, free "Solo at Web Summit: dinner the night before" event, with RSVP on Luma and a link to /e/web-summit-lisbon. Someone has to host it, so this only happens if a person will be in Lisbon on 8 Nov | Nima decides | 10 to 25 RSVPs, 5 to 15 alerts |
| 2 | Lisbon Digital Nomads on Meetup (25,884 members, weekly Thursday meetup; "If you'd like to sponsor any of our events, feel free to get in touch") | The live nomad community in Lisbon | Email the organizers to offer a disclosed Web Summit week board for their members, or a sponsored round of drinks. No posting in the group without their yes | Nima approves the spend | 5 to 15 |
| 3 | Lisbon event newsletters (Open Coffee Lisbon, The Lisbon Letter, Lisbon Weekly, ExpatsEvent) | They take free community event submissions | Submit the event from row 1 | Me, once the event exists | 3 to 10 |
| 4 | r/solotravel weekly "General Chatter, Meetup and Accommodation" thread (4.35M) | The one place on the sub where meetups and self promotion are allowed | One honest, disclosed comment a week: "If you are in Oaxaca for Muertos, Chiang Mai for Yi Peng or Lisbon for Web Summit, there is a board where you can see who overlaps" | A real account Nima controls | 3 to 10 |
| 5 | r/ThailandTourism (985K: "You are welcome to post links to blog posts... spam is not") and r/chiangmai (26K: links "as part of a discussion") | Yi Peng questions spike in November | One genuinely useful Yi Peng 2026 post (what is confirmed, the 2025 lantern red zones and flight cutoffs, free versus ticketed), with the page as the source link | Same | 5 to 15 |
| 6 | Social hostels: Stamps Backpackers (Chiang Mai, publishes its own Yi Peng guide), Casa Angel and Hostal Central (Oaxaca), Home Lisbon and Sunset Destination (Lisbon) | Travelers who are already there and want company | Email them offering a free one-page "what travelers wish they knew" sheet with a QR code to the week board, for reception. With permission only | Me drafts, Nima sends | 5 to 15 per hostel that says yes |
| 7 | Our own Facebook Page (Ruin My Trip Travelers) and Instagram @ruinmytripcom | Already ours | 3 posts per ecosystem: what the 2026 situation is, a warning, and the "I'm going" card | Me, with approval | Low, but it compounds with shares |
| 8 | Small creators: A Year in Oaxaca (substack), Nomadic Notes, The Extended Stay | Readers are exactly these travelers | Offer them the warnings and the board as a free reader resource, not an ad | Nima sends | 0 to 10 |
| 9 | Personal network | The first overlap is easiest between people who know each other | Nima and anyone on the team who is really going somewhere posts a real trip and shares the card | Nima | 2 to 5 |
| 10 | Founder posts: r/indiehackers (Show IH, once), r/startups monthly thread, Show HN only once the site works without signup | Feedback, not travelers | One post later, after the first overlaps | Later | Feedback |

**Ruled out:** these forbid it or are dead.
- r/travel, r/digitalnomad, r/Thailand, r/mexico, r/PortugalExpats, r/femaletravels, r/shoestring
- Couchsurfing: its policies ban promotion and events created to promote something.
- Girls LOVE Travel
- r/MexicoTravel, which now redirects.

**r/travelpartners:** only for a real trip by a real person. Links are removed as contact information, and messaging the posters would be spam.

**Cadence:**
- Weeks 1 to 2: Oaxaca, because it starts in 30 days. Hostels, reddit help posts, our pages.
- Weeks 2 to 6: Lisbon, the Web Summit event plus the newsletters.
- Weeks 4 to 8: Chiang Mai, the Yi Peng post plus Stamps.

**Weekly check:** for each channel, compare the `phase` numbers by `acq_source`. Drop any channel that produces visits but no alerts after two posts.

## 4. Q7: the final 14 redirects (NOT executed)

All 14 were checked on 2026-10-01. **Every check passes.**

| /in/{c} | 301 to | Target status | Target robots | Canonical self | Redirect chain | Words (source / target) | Target in sitemap | Google | Every /in city link present on target |
|---|---|---|---|---|---|---|---|---|---|
| colombia | /travel-buddies/colombia | 200 | index | yes | none | 87 / 468 | yes | indexed | yes (2) |
| croatia | /travel-buddies/croatia | 200 | index | yes | none | 61 / 455 | yes | indexed | yes (1) |
| france | /travel-buddies/france | 200 | index | yes | none | 85 / 463 | yes | indexed | yes (2) |
| greece | /travel-buddies/greece | 200 | index | yes | none | 86 / 576 | yes | indexed | yes (2) |
| iceland | /travel-buddies/iceland | 200 | index | yes | none | 61 / 478 | yes | indexed | yes (1) |
| italy | /travel-buddies/italy | 200 | index | yes | none | 144 / 523 | yes | indexed | yes (4) |
| japan | /travel-buddies/japan | 200 | index | yes | none | 113 / 546 | yes | indexed | yes (3) |
| mexico | /travel-buddies/mexico | 200 | index | yes | none | 144 / 509 | yes | indexed | yes (4) |
| morocco | /travel-buddies/morocco | 200 | index | yes | none | 86 / 470 | yes | indexed | yes (2) |
| peru | /travel-buddies/peru | 200 | index | yes | none | 85 / 475 | yes | indexed | yes (2) |
| portugal | /travel-buddies/portugal | 200 | index | yes | none | 88 / 479 | yes | indexed | yes (2) |
| spain | /travel-buddies/spain | 200 | index | yes | none | 61 / 466 | yes | indexed | yes (1) |
| thailand | /travel-buddies/thailand | 200 | index | yes | none | 116 / 546 | yes | indexed | yes (3) |
| vietnam | /travel-buddies/vietnam | 200 | index | yes | none | 91 / 510 | yes | indexed | yes (2) |

- **Links that change in the same commit:**
  - `views/destination.php:3`: the visible breadcrumb.
  - `app/controllers.php:403`: the city BreadcrumbList.
  - `views/explore.php:34`: the country chips.
  - `app/controllers.php:257`: the /in page's own breadcrumb, for the 42 that stay.

  All of them go through one helper that returns the travel-buddies URL for these 14 and `/in/{c}` for everyone else.
- **Sitemap:** no /in URL is in the sitemap, so there is nothing to remove. All 14 targets are already in it.
- **Redirect chains:** none. Each target answers 200 directly.

## 5. Outstanding audit items

**1. Venue pages (/p/).**
- **How much of the index they are:** 338 venue URLs drew 2,025 of the site's 3,151 impressions over 90 days, and all 6 clicks. They are not in the sitemap; Google found them by crawling city pages. They dominate because they are the only pages that match a specific search: a venue's name.
- **Two kinds:**
  - Editorial pages for landmarks, around 900 to 1,050 words: Book of Kells, Vasa Museum, the Whitney. They rank at positions 58 to 68 and get impressions but never clicks.
  - Short pages built from map data for small venues, around 270 to 310 words: Le Rapido in Paris, Nini's Coffeebar in Amsterdam. They rank around position 8 to 9 for the venue's own name, and they are where the clicks came from.
- **Recommendation:** keep them indexed and make no change now. They are the only pages earning clicks, and removing them would cost the little traffic the site has.
- **Do not build more of them.** Improve them only as members review them.
- **Revisit after 90 days.** Any page with zero impressions in that window becomes a candidate for consolidation into its city page, not for noindex.

**2. Tag pages.**
- **What is in the sitemap:** /tags plus 5 tags (bangkok, lisbon, rome, tokyo, venice). Each holds 1 post and about 55 words.
- **Why they are a problem:** each is a thin duplicate of its city page. They take crawl attention and add nothing for search.
- **Safest change:** take them out of the sitemap and keep the pages live and indexable, which respects the never-noindex rule. A tag would return to the sitemap automatically once it holds at least 3 posts.
- **Status:** awaiting approval. This is a sitemap change, so it was not made.

**3. Render free plan.**
- **Measured 2026-10-01**, laptop to Oregon, 5 samples each:

| Page | Time to first byte |
|---|---|
| /healthz (network floor) | 0.18 to 0.27 s |
| Cached city or event page | 0.17 to 0.26 s |
| Uncached venue page | 0.28 to 0.70 s |

  The free instance's 0.1 CPU is visible only on uncached pages, and is about 0.1 to 0.45 s of server time.
- **Spin-down:** it is not hurting crawling. The Cloudflare keepalive pings every 10 minutes, so the service does not spin down.
- **Database:** paid (basic_256mb), so it does not expire.
- **What it is costing:**
  - slower uncached pages;
  - the 60-second page cache lives in the container's temporary storage, so every deploy empties it;
  - a single instance.
- **Recommendation:**
  - No upgrade needed now. Optimise first by extending the signed-out page cache to /p and /e pages.
  - Upgrade (Starter) only if Search Console crawl stats show response time above 1 s, or once real traffic arrives.
  - No cost was incurred.

**4. Google sign-in is still off.** The setup step in Google Cloud needs your explicit yes to accept the Google API Services User Data Policy.

## 6. How to read the numbers

- `/cron/funnel?key=CRON_KEY&days=7`, then the `phase` and `activation` blocks.
- `/admin/funnel` for the activation table.
- Search Console: `scripts/gsc_report.py` (token) with the page filters oaxaca, chiang-mai, lisbon and `/e/`.
