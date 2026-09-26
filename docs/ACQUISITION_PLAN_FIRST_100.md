# RuinMyTrip launch: the first 100 real members

Written 2026-09-25 (Pacific). Product is frozen except bug fixes. Every time below is Pacific.
Nothing is posted, joined or messaged without Nima's go ahead in his own signed in session.

## The engine (runs without Nima)

The launch does not rest on personal messages. Those are for the first ten members only. The
repeatable part is a pipeline that keeps our own accounts posting every day and turns what happens on
the site into more posts:

| Piece | What it does | Runs |
|---|---|---|
| Content bank and calendar | 39 posts, 30 day calendar from 2026-09-28 to 2026-10-27, no theme twice in a row | Written; extended monthly |
| `scripts/social_kit.py` | Carousels, captions, hashtags, tracked links, schedule for all three platforms | On demand; 30 days already built in `~/rmt_social/2026-09-28/` (90 posts) |
| `/cron/social` + `scripts/social_from_site.py` | Turns site activity into candidates: cities with several travelers going, countries with open buddy requests (counts only, never names), questions, warnings | Daily, 07:30, hidden task "RMT Social Daily" |
| `scripts/social_daily_check.py` | The next three days of links answer and show their banner, share images load, the trip form renders, arrivals and engaged visitors were recorded | Daily, same task; results in `~/rmt_social/checks/` |
| Share loops on the site | Trip (share, ask a friend who has been), buddy request (share your request, send to a friend), question, warning pages, city, empty matches | Live |

Review rule: counts and the team's own questions go out as they are; a member's words and any
warning that makes a claim about a place wait for a person to approve them.

## Ready now

| Piece | Where | State |
|---|---|---|
| 14 days of posts, one a day, every theme | `docs/social/content_bank.json` (calendar from Mon 2026-09-28) | Ready |
| Carousels 1080x1350, copy per platform, tracked links, schedule | `~/rmt_social/2026-09-28/` (42 posts), rebuilt by `python scripts/social_kit.py --start 2026-09-28` | Ready |
| Landing from a post | A visitor from a Facebook post, or from the Instagram or TikTok bio link, sees the question they came from and "Answer it here", which opens the right composer. Signed out answers are written first and posted after signup | Live |
| Share previews | City, trip, question, profile and buddy pages share with their own card image; `/plan?d=city` links show the city | Live |
| Channel table | `/admin/funnel`: search, facebook, instagram, tiktok, reddit, direct, referral, each with landed, engaged, CTA clicked, trip form, acted, signup started, signed up, first contribution, came back | Live |
| Referral tracking | A member's invite link (`?ref=username`) counts as referral; member shares carry `utm_medium=share` | Live |

## Setup, once, about 60 minutes of Nima's time

Create in this order, because the first reaches strangers and the others do not:

1. **TikTok account** (15 min). Handle `ruinmytrip`, backups `ruinmytripcom`, `ruinmytrip.travel`. Name
   RuinMyTrip. Bio and profile image in `docs/TIKTOK_LAUNCH.md`. Bio link
   `https://ruinmytrip.com/?utm_source=tiktok&utm_medium=bio&utm_campaign=profile`.
2. **Instagram professional account, linked to the Ruin My Trip Page in Meta Business Suite** (20 min).
   Same handle order. Bio link `https://ruinmytrip.com/?utm_source=instagram&utm_medium=bio&utm_campaign=profile`.
   Linking it lets one Business Suite pass schedule Facebook and Instagram together.
3. **Chrome signed in as the Badri profile** (the Page admin), and TikTok signed in on the same Chrome
   (5 min). Then I schedule week one in Business Suite and TikTok Studio (about 30 minutes, mine).
4. **Your personal Facebook profile joins 5 groups** (15 min, day 3 below). Personal, not the Page:
   Pages cannot post in groups.

## The 14 days

"You" is Nima. "Me" is the session. Metrics are read from `/admin/funnel?days=1` every evening by me
and reported in one line. No channel is judged before it has 50 engaged visitors.

| Day | Date | Posted on Facebook 10:00, Instagram 12:00, TikTok 18:00 | Outreach | Links used | You | Me |
|---|---|---|---|---|---|---|
| 0 | Sat 26 to Sun 27 Sep | Nothing | None | | Setup 1 to 3 above | Schedule week one once signed in |
| 1 | Mon 28 Sep | Worst tourist trap (tourist traps) | Message 10 friends with a real upcoming trip (text below) | `/plan?utm_source=referral&utm_medium=dm&utm_campaign=friends`, post link `/ruined` | 10 messages, 15 min | Read metrics |
| 2 | Tue 29 Sep | Hidden fee confession (hidden fees) | 10 more friends | same | 10 messages, 15 min; reply to comments, 5 min | Metrics; title experiment read |
| 3 | Wed 30 Sep | First night alone in a new city (solo travel) | Join 5 Facebook groups: 2 solo travel, 3 for calendar cities (Bangkok, Japan, Mexico City, Paris, Porto). Read each group's pinned rules | none in groups yet | Join and read rules, 15 min | Metrics |
| 4 | Thu 1 Oct | The scam that almost got you (travel scams) | 3 helpful comments in the groups, no links. Reddit: answer 2 questions in r/solotravel from your own account, no links | none | 15 min | Metrics |
| 5 | Fri 2 Oct | Window or aisle (destination debates) | Ask the members who joined to send their trip page to whoever is traveling with them (Share on the trip page) | trip pages | 5 min | Metrics; list members with trips |
| 6 | Sat 3 Oct | Would you travel with a stranger (travel buddy) | Group comments, 10 min | none | 10 min | Metrics |
| 7 | Sun 4 Oct | Funniest travel disaster (travel disasters) | Week review | | 10 min reading my summary | Week one report by channel; schedule week two |
| 8 | Mon 5 Oct | Attraction not worth the line (overrated places) | First group post, only in a group whose rules allow it or whose admin said yes (Let's Go Solo if approved). Disclose you built the site | `/travel-buddies/{country}` with `utm_source=facebook&utm_medium=group&utm_campaign=launch&utm_content=group_1` | Post once, 10 min | Metrics; draft the group post for that group |
| 9 | Tue 6 Oct | Unpopular travel opinion (unpopular opinions) | 10 more friends, or a group chat you are in | `/plan` referral link | 15 min | Metrics |
| 10 | Wed 7 Oct | What visitors to your city get wrong (local tips) | Reddit: read the rules of r/travelpartners and r/TravelBuddies in a browser; post a real trip of yours only if allowed | `/buddies` with `utm_source=reddit&utm_medium=post&utm_campaign=launch` | 15 min | Metrics |
| 11 | Thu 8 Oct | Your rule for tourist trap restaurants (tourist traps) | Second group post, a different group, different text | tracked group link `group_2` | 10 min | Draft it |
| 12 | Fri 9 Oct | Rental car horror stories (hidden fees) | Ask every member to share their trip page with one friend going to the same place | trip pages | 5 min | Metrics |
| 13 | Sat 10 Oct | Going to Japan this season (travel buddy) | Group comments | none | 10 min | Metrics |
| 14 | Sun 11 Oct | Porto or Lisbon (destination debates) | Review | | 15 min | Two week report: channel table, members, trips, overlaps; plan weeks 3 and 4 from what worked |

Every Facebook post carries its own `utm_content` (the post id), so each one is readable on its own.
Instagram and TikTok are read per platform through their bio links.

## The friends message (you send it yourself, one at a time, never twice to the same person)

> I built a site for finding people who are traveling to the same place at the same time. If you have
> a trip coming up, would you post it? It takes a minute and it would really help me get it started.
> https://ruinmytrip.com/plan?utm_source=referral&utm_medium=dm&utm_campaign=friends

Only people with a real trip. No trip, no ask.

## Milestones

| Members | Channels | Exact actions | What gets posted | What you do |
|---|---|---|---|---|
| 0 to 10 | Your contacts; TikTok and Instagram start | 20 to 30 personal messages over days 1, 2 and 9; the daily posts | The calendar, one post a day on three platforms | Setup; the messages; 5 minutes of comment replies a day |
| 10 to 25 | TikTok, Instagram, members inviting the person they travel with | Members invite co travelers from the trip page; 5 groups joined and warmed with comments | Calendar weeks 1 and 2 | Ask each member to invite their travel partner; group comments |
| 25 to 50 | Facebook groups that allow it; Reddit participation | 2 to 4 compliant group posts, one per group, different text each; Reddit answers with no links, one buddy post where the rules allow | Weeks 3 and 4 built from whichever themes got the most comments | One group post every 3 days; Reddit 10 minutes a day |
| 50 to 100 | The channel with the best engaged to member rate, plus member sharing | Double the posting on the winning channel; stop the one with 50+ engaged visitors and no members | More of what worked | Keep replying; decide on a Page token for hands off posting |

## Referral loop, kept simple

No rewards. Each step already exists; the launch just asks people to use it:

1. **Invite someone joining you.** On a trip page, Invite adds a co traveler who already has an
   account (by username; they get the trip and an email). Someone without an account gets the trip
   page link through Share, and joins from it. Asked on days 5 and 12.
2. **Share your trip.** Every public trip, city, question and buddy page has Share with its own card
   image, and the link carries `utm_medium=share`.
3. **Nobody overlaps you yet? Send it to a friend going there.** The matches page asks exactly this
   when a city is empty.
4. **Your invite link.** `/invite` gives each member `ruinmytrip.com/?ref=username`; a signup through
   it names the member who sent it, and it counts as referral in the channel table.
5. **Everyone who joins makes the site less empty.** A new trip shows on the front page, in the city,
   on the buddy search and in "Happening on RuinMyTrip", and tells anyone with overlapping dates.

## What can run without you, and what cannot

| Step | Status |
|---|---|
| Making posts, slides and copy | Automatic |
| Daily metrics readout | Automatic, from `/cron/funnel` |
| Scheduling Facebook and Instagram | Me, weekly, in your signed in Chrome. Fully hands off only with a Page access token you choose to create |
| Scheduling TikTok | Me, weekly, in TikTok Studio in your signed in Chrome |
| Personal messages, group posts, Reddit | You. These are a person talking, and they only work because they are |
| Replying to comments | You, or me in your session if you say so per week |
