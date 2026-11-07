# RuinMyTrip monetization options (researched 2026-11-07)

**Where things stand.** About 1,900 impressions and 9 clicks per 28 days (GSC, Oct 2026). Essentially no members. Nothing is wired up yet: `/affiliate` (`views/legal/affiliate.php`) is a disclosure page only, and the only "affiliate" code in `app/` is `content_quality.php`, which blocks referral/affiliate links in **user** content. Keep that block. Program terms below come from each program's own page. Where a program publishes terms only through a network, the network page is cited and labelled. Anything I could not find on an official page is marked "not published".

## Ranked options

| # | Option | Feasible now | Value now / at 10x (qualitative) | Traffic requirement | Complexity | Trust risk |
|---|---|---|---|---|---|---|
| 1 | Flight-claim referral (AirHelp) | Yes | Negligible / low, with a clear page fit | None ("No minimums") | Low | Low if limited to a rights page |
| 2 | Tickets/tours: Tiqets, GetYourGuide, Viator (direct or via Travelpayouts) | Yes | Negligible / low | Viator: none. GYG/Tiqets: not published | Low | Low on ticket-price posts; high near reviews |
| 3 | Travelpayouts as the umbrella account | Yes | Same programs, one dashboard | Must be a content site | Low | Same as the programs it carries |
| 4 | eSIM (Airalo, Holafly) | Yes | Negligible / low | Not published | Low | Low |
| 5 | Hotels: Booking.com, Expedia Group | Yes, but weak | Negligible / low (Booking cookie is one session via TP) | Not published | Low | **High** next to city/place pages with reviews |
| 6 | Travel insurance (SafetyWing, Heymondo, VisitorsCoverage, World Nomads) | Technically yes | Low / low-medium | Not published | Medium (compliance wording) | Medium: advice and licensing limits |
| 7 | Display ads (AdSense, Ezoic) | Can apply; low odds | Negligible / negligible | None stated; AdSense wants "an audience" | Medium | Medium (ad clutter, low-quality ads) |
| 8 | Mediavine / Raptive | No | n/a / still no | Journey 1,000 Tier-1 sessions; Raptive 25,000 PV | Low once eligible | Low-medium |
| 9 | Sponsored placements (tourism boards, hosts, businesses) | No buyers yet | n/a / possible | None, but needs audience | Medium | **Highest** |
| 10 | Supporter membership | Not yet | Negligible / low (needs members) | None | Medium-high | Low if it buys nothing that affects ratings |

These are qualitative rankings only. No revenue or traffic projections are made.

## Program terms (cited)

**Flight claims**
- **AirHelp:** 15% of AirHelp's fee on successful claims, 20% on AirHelp+ memberships, 30-day cookie, "No minimums", join directly or through a network. Payout threshold not published. <https://www.airhelp.com/en/affiliate/>
- **ClaimCompass:** www.claimcompass.eu and its partners page currently 301-redirect to airhelp.com (checked 2026-11-07), and affiliate.claimcompass.eu returned 502. Treat it as unavailable or folded into AirHelp.
- **Compensair:** I could not reach an official affiliate page. On Travelpayouts the payout is €12, €8 or €5 per processed claim depending on the airline, with a 30-day cookie. <https://travelpayouts.com/en/offers/compensair-affiliate-program>

**Tours, tickets and activities**
- **Viator:** 8% on completed bookings, 30-day cookie, "no traffic or follower minimums". Bank payouts monthly with a $50 minimum; PayPal weekly with no minimum. <https://partnerresources.viator.com/>
- **GetYourGuide:** partner.getyourguide.com renders no readable terms (not published to me). Via Travelpayouts: 8% of booking amount, 31-day cookie. <https://www.travelpayouts.com/en/offers/getyourguide-affiliate-program>
- **Tiqets:** official affiliate page not found (tiqets.com affiliate URLs return 404). Via Travelpayouts: 3.5–8%, 30-day cookie. <https://travelpayouts.com/en/offers/tiqets-affiliate-program>
- **Klook:** the official page (affiliate.klook.com) shows no readable terms. Via Travelpayouts: 5% (2% on "Special Activities"), 30-day cookie. No brand bidding and no unsupplied discount codes. <https://www.travelpayouts.com/en/offers/klook-affiliate-program>

**Aggregator**
- **Travelpayouts:** open to "anyone with a public channel". It says it is free, takes no cut, and pays monthly. Payout minimum depends on the withdrawal method; I could not read the support article (403). <https://www.travelpayouts.com/en/> · <https://support.travelpayouts.com/hc/en-us/articles/360012173160-Minimum-amount-for-revenue-payout>

**Hotels**
- **Booking.com:** registration now goes through CJ ("complete your registration with CJ"). Rate, cookie and threshold are not published on Booking's page. <https://www.booking.com/affiliate-program/v2/index.html>
  - Via Travelpayouts: the page shows both 5% and a "maximum of 4% TTV" per stayed reservation (they conflict). Cookie is "One session". Confirmation takes 60–90 days after checkout, and only content sites are accepted. <https://www.travelpayouts.com/en/offers/bookingcom-affiliate-program>
- **Expedia Group:** the program has moved to the Creator Program (affiliates.expediagroup.com redirects there). "Up to 4%" on Travel Shops, "Anyone can apply", paid through Partnerize. Cookie and threshold not published on that page. <https://creator.expediagroup.com/>

**eSIM**
- **Airalo:** "up to 10%", apply through Impact. Cookie not published on the official page. <https://partners.airalo.com/solutions/affiliates>
- **Holafly:** "10% commission on every sale". Its FAQ ties commission to an affiliate discount coupon. Cookie not published. <https://esim.holafly.com/affiliate-program/>

**Travel insurance**
- **SafetyWing:** a flat fee "benchmarked at approximately 10% of total premium amount". Paid within the first ten working days of the month. **No paid advertising**, and only SafetyWing-supplied materials may be used. <https://safetywing.com/ambassador> · <https://intercom.help/safetywing/en/articles/9796053-the-ambassador-program>
- **World Nomads:** runs through CJ and pays a referral fee per confirmed quote (amount not published). Affiliates are "not licensed or authorised… to provide financial services". They **may not advise, recommend, call a plan "comprehensive"/"best", or compare it with other insurance**. Original content needs World Nomads review before publication. <https://www.worldnomads.com/partnerships/affiliates/content-guidelines>
- **Heymondo:** "attractive referral commissions" (rate not published), 30 days after last click. Heymondo is a broker registered with Spain's DGSFP (J3422). <https://heymondo.com/affiliate-program>
- **VisitorsCoverage:** its FAQ says you do not need to be a licensed agent; referral fees are paid monthly once the balance is $100 or more. The page returned 403 to me, so I am relying on a search excerpt; re-check it. <https://www.visitorscoverage.com/partner/faq/>

**Display ads**
- **AdSense:** applicant must be 18+, content must be "high-quality, original, and attract an audience", and you must have access to the HTML. No numeric traffic minimum is stated. <https://support.google.com/adsense/answer/9724>
- **Journey by Mediavine:** 1,000 sessions from Tier-1 countries in 30 days. **Mediavine:** 50,000 sessions per month. <https://www.mediavine.com/application-requirements/> · <https://www.mediavine.com/mediavine-application-process-ga4/>
- **Raptive:** at least 25,000 pageviews per month, mostly US/CA/UK/AU/NZ traffic. <https://raptive.com/creators/>
- **Ezoic:** the FAQ's requirements section was empty (not published). Ezoic's 2021 Access Now PDF says pageview limits were removed. <https://www.ezoic.com/wp-content/uploads/2021/02/Basic-Ad-Policy-Requirements-for-Ezoic-Access-Now.pdf>

**Sponsored placements and reviews (rules)**
- **Google:** mark paid links `rel="sponsored"` (`nofollow` is "still acceptable"). <https://developers.google.com/search/docs/crawling-indexing/qualify-outbound-links>
  - Paid links are "not a violation… as long as they are qualified". "Thin affiliation" pages are spam. <https://developers.google.com/search/docs/essentials/spam-policies>
- **FTC:** disclosures must be clear and conspicuous, and "the closer… to your recommendation, the better". A disclosure at the bottom of a page, or behind a DISCLOSURE/LEGAL link, is inadequate. "Affiliate link" alone may be inadequate; "Paid link" next to the link is adequate. <https://www.ftc.gov/business-guidance/resources/ftcs-endorsement-guides-what-people-are-asking>
- **FTC Consumer Reviews rule (16 CFR 465, effective 2024-10-21):** bans buying or selling reviews, fake (including AI-generated) reviews, and review suppression. <https://www.federalregister.gov/documents/2024/08/22/2024-18519/trade-regulation-rule-on-the-use-of-consumer-reviews-and-testimonials>

**Premium membership.** There is no program to cite and I have set no prices.

## Recommendations

**What to apply for first.** Apply only where there is a page that already serves the need.
1. **AirHelp** (direct). Fits a "Trip went wrong: what you can claim" page, which ties directly into the "what went wrong" reviews.
2. **Travelpayouts**, then join **Tiqets + GetYourGuide + Klook** inside it. This covers the ~100 ticket-price posts with one account.
3. **Viator direct**, as a second ticket option. It has no minimums and offers PayPal with no payout minimum.
4. **Airalo** (through Impact), only if a real connectivity or eSIM utility section exists.

Defer the rest:
- **Booking.com/Expedia:** the one-session cookie via TP is weak, and the hotel links would sit next to reviews.
- **Insurance:** high compliance burden.
- **AdSense/Ezoic:** ads on a site recovering from a spam-update drop add risk and earn nothing at this traffic level.
- **Mediavine Journey:** revisit when GA shows 1,000 Tier-1 sessions in 30 days.
- **Sponsorships and membership:** revisit when there is an audience.

**Where each one fits (allowed surfaces only)**
- **Ticket-price and tourist-tax posts:** one labelled "Book tickets" box below the factual price table, never inside it. The price data must come from the venue, never from the partner's feed.
- **Tourist-tax calculator:** no links in the result itself. At most an optional labelled "Planning this trip?" panel underneath.
- **Claims page:** an explanation of EU261 and similar rights first, plus "you can claim yourself for free", then the AirHelp option, labelled.
- **City pages:** a separate, labelled "Partner links" section, visually distinct and placed after community content. No hotel links inline.
- **Insurance, if added later:** use only the insurer's supplied materials, with no "you need", "best" or comparison wording (World Nomads 3.1–3.5). No insurance comparison table.

**Never place money on:** review cards, rating widgets, review submission or editing, place rankings or sort order, the travel-buddy finder, the hosts and locals free-stay pages, member profiles, or messages.

**Required disclosures and attributes**
- Put a short line next to every partner block, e.g. "Paid link: we earn a commission if you book. It never affects reviews or ratings." Link it to `/affiliate`, but the `/affiliate` page alone does not satisfy the FTC.
- Every affiliate or sponsored `<a>` needs `rel="sponsored noopener"` (plus `nofollow` if preferred) and should open via a server-side `/go/...` redirect that is itself `noindex` and disallowed.
- Sponsored content gets a visible "Sponsored" label at the top, on its own URL, kept out of review aggregates.
- Update `/affiliate`. It currently says creators "may earn from… affiliate bookings" and businesses "may pay for clearly marked promotion", while `content_quality.php` blocks affiliate links from users. Make the page match the real policy and list the active programs.

**What must NOT be done**
- No paid reviews, no paid rating boosts, no "verified partner" badges, and no sorting places by commission (16 CFR 465).
- Never hide, delay or soften negative reviews of partner businesses. Never let a partner's commission decide which tour or hotel is shown beside a review.
- No affiliate links inside user reviews. Keep the `content_quality.php` block, and do not exempt staff.
- No mass-inserted links (e.g. Travelpayouts Drive or LinkSwitcher auto-linking across all ~1,200 place pages). That is the "thin affiliation" pattern, on a site already hit by a spam update.
- No insurance advice or comparisons. No paid ads for SafetyWing. No brand-term bidding (Klook). No unsupplied discount codes (Klook; Viator on Awin).
- No sponsored articles from third parties that trade on the domain (site reputation abuse).
- No "sponsored" placements without `rel="sponsored"`.
- No "supporter" tier that buys visibility, badges on reviews, or moderation leniency.
