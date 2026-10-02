# RuinMyTrip growth plan, 2026-10-02

Goal (Nima, 2026-10-02): a social travel review site. People post reviews of places they went,
read other travelers' reviews before a trip, find people to travel with, and message each other.

## Where it stands (measured 2026-10-02, /cron/funnel 30 d and GSC 28 d)

| | |
|---|---|
| Google, 28 days | 2,198 impressions, 6 clicks, avg position 21; every click on a /p/ venue page |
| Engaged human visitors, 30 days | 44 (15 from search) |
| Members | 1 real, 0 trips, 0 buddy posts, 0 member reviews, 0 messages |
| Product | built: reviews with photos, place pages (1,393), trips and overlap matching, buddy finder, mutual opt in messaging, city talk, meetups, travel map, alerts, PWA |

The product already does everything in the goal. What is missing is people. A review and buddy
site with nobody on it cannot rank and cannot convert, and Google alone will not fix that for a
three month old domain with no backlinks: search follows content and links, and both come from
people.

## How sites like this got their first users

* **Tripadvisor**: launched in 2000 as a directory of other people's content, added "write a
  review" as an afterthought; the review button is what grew it. Place pages were the SEO engine.
* **Yelp**: the founders emailed friends asking for reviews, then hired local community managers
  and threw parties for its most active reviewers in one city (San Francisco) before expanding.
* **Couchsurfing**: the founder's own network and travel forums; one city of hosts at a time.
* **Nomad List**: founder built in public, launched on Product Hunt and Hacker News, every launch
  brought links and the first few thousand users.
* **GAFFL, JoinMyTrip**: programmatic "travel buddy to X" pages, but only after they had real
  listings to put on them.

Pattern: real content from people the founder already reaches, concentrated in one place, plus
launch platforms that give both users and backlinks. Then search compounds.

## The plan

### 1. Make every visit able to become a member (site work, Claude, no approval needed)

* DONE 2026-10-02: write first, join after for reviews. The review form no longer sits behind a
  sign in wall; the review is held, the account step shows it back, it publishes on confirm.
* Place pages (/p/) are the only pages Google sends clicks to. Make them the review site's front
  door: one tap "Been here?" star rating at the top, the form one click away, and a clear line on
  what the place costs and what goes wrong there once members say so.
* Sign in with Google: code is live and switched off. Needs Nima to tick the Google API User
  Data Policy in the GCP console (two minutes), then Claude finishes the client and env vars.
* Post review prompt: two days after a trip ends, "How did Lisbon go?" email.

### 2. Seed real content from people Nima already reaches (Nima + Claude)

* Nima writes 10 real reviews of places he has actually been, on his own account. Claude makes
  it fast: a list of places from his past trips already on the site, one link per place.
* Ask 10 to 20 friends and family for 5 reviews each of real trips. Target 200 genuine reviews
  in 30 days. Nothing invented, nothing posted as anybody but the writer.
* Every review publishes on a place page Google already indexes, which is exactly what those
  pages need to rank above position 20.

### 3. Launch platforms and directories (one time, gives users and backlinks)

Product Hunt, BetaList, Hacker News (Show HN), Indie Hackers, AlternativeTo (as an alternative to
Tripadvisor and Couchsurfing), SaaSHub, startup and travel app directories. Each needs an account
in Nima's name; Claude prepares every listing, Nima says go per platform.

### 4. Nima's own audiences

BetLegend YouTube, the X account and the sites he owns already reach people. One honest mention
or link each ("I built a travel site, write about your worst trip") is worth more than months of
posts on a three week old Facebook Page. Needs his go per property.

### 5. Communities where travelers ask for buddies

r/travelpartners and r/solotravel (answer real posts, link only where allowed), Facebook
groups for specific events. Replies to real people, not broadcast posts. Needs his go per batch.

### 6. One link earning asset (needs Nima's yes, it is content)

The only non venue pages Google ranks are the 2026 tourist tax posts (Milan city tax at position
7). A single "tourist taxes 2026, every city" table, kept current, is what journalists and travel
bloggers cite. Proposed, not built: the editorial stop of 2026-09-08 stands until Nima says.

### 7. Stop

* No more Facebook Page scheduling as the main channel: 135 visits, 2 human, 0 signups.
* No new cities or landing pages until a city has five real upcoming trips.

## Scoreboard (weekly, /cron/funnel)

Members, member reviews, places with a member review, trips, messages, GSC clicks. Targets for
30 days: 50 members, 200 member reviews, 300 GSC clicks per month.
