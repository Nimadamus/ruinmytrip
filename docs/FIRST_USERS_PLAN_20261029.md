# First active users: a free and low cost plan (2026-10-29)

Baseline (KPI.md, people only, 30 days to 10-29): 71 verified humans, 1 signup, 0 reviews,
0 trips, 0 buddy posts. Google 28 days: 711 impressions, 7 clicks. Traffic is the limit, and until
10-29 every new member was also locked out of travel buddies (fixed, 8a28003).

The product's one honest hook today: **post your dates, see who else is going, nobody can message
you until you accept.** Every channel below sends people to a page where that is one tap away.

## Goal for the first 30 days (measurable in KPI.md and the Tagged links table)

- 25 confirmed members, 10 buddy posts or trips with dates, 10 traveler reviews.
- Every link tagged `utm_source/utm_campaign/utm_content` so the Tagged links table credits it.

## Channels, in order of expected yield per hour

| # | Channel | Cost | Who does it | Lands on | Why it can work |
|---|---|---|---|---|---|
| 1 | Friends and family first wave: 20 personal messages asking for one review of a place they know and their next trip's dates | free | Nima (his contacts) | `/review/new?destination=..&src=friends`, `/plan` | The site needs its first 10 real reviews before strangers trust it. Links ready in ~/rmt_campaign1/FRIEND_LINKS.md |
| 2 | Answer real "looking for a travel buddy" posts: r/travelpartners, r/solotravel weekly threads, Facebook travel buddy groups, Couchsurfing hangouts forum. Reply only where a person asks; one honest line plus the city page link | free | Nima posts (Reddit bans AI text; replies drafted by Claude, rewritten by him) | `/d/<city>/travelers`, `/travel-buddies/<country>` | Highest intent there is: they are asking for exactly this |
| 3 | Cruise roll calls: Cruise Critic roll call threads per sailing. Post the matching `/cruise-buddies/<line>` page only where links are allowed | free | Nima | `/cruise-buddies/<line>` | Cruise passengers already look for shipmates by date |
| 4 | Instagram @ruinmytripcom: 2 carousels a week from verified facts (fees, tickets, scams), CTA "what went wrong on your trip?" Bio link must be added on the phone | free | scheduled posts already in rmt_social; bio link Nima | `/map`, city pages | Already running; needs the bio link to convert at all |
| 5 | Facebook Page posts after the restriction lifts (~11-08), then destination groups that allow links (Oaxaca from 11-03 drafts exist) | free | Nima approval per batch | `/e/<event>`, city pages | Day of the Dead group post already brought 6 humans, 1 came back |
| 6 | Search: improve pages already ranking 5 to 20 (place titles now say prices; Greece buddy page ranks ~18 for "travel buddies"); one quality guide a week linked from the buddy hub | free | Claude | `/blog/...`, `/travel-buddies/...` | Compounds; zero cost |
| 7 | Free directories and launch sites: BetaList, AlternativeTo, Indie Hackers, Product Hunt (copy in docs/LAUNCH_LISTINGS.md) | free | Nima signs in, Claude fills | home | One time spike, some backlinks |
| 8 | Optional paid test after 25 members: 50 dollars of Reddit or Meta ads on one city buddy page, stop if cost per confirmed member is over 5 dollars | 50 dollars | Nima approval | one city buddy page | Only worth it once pages show real people |

## Weekly loop (Claude)

1. Monday: read KPI.md and the Tagged links table; drop the channel with zero humans after two weeks.
2. Draft the week's replies, carousels and one guide; Nima approves and posts where an account is his.
3. Fix whatever the funnel shows people stopping at (review form start, join, age step, buddy post).
4. Never fabricate members, reviews, matches or activity; empty states stay honest.

## What needs Nima

- Instagram bio link (phone only), Reddit/Facebook/Cruise Critic posting under his accounts,
  the friends wave, directory sign ins, and any spend.
