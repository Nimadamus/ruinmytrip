# RuinMyTrip outreach drafts (2026-10-30)

Status: DRAFTS ONLY. Nothing here has been posted, sent, joined or submitted. The owner reads each draft, edits it into his own words, and posts it himself from his own accounts.

Plan this builds on: `docs/LAUNCH_CAMPAIGN_20261029.md` (the plan to act from). Supporting: `REVIEWS_CAMPAIGN_20261030.md`, `LAUNCH_LISTINGS.md`, `ACQUISITION_FACEBOOK.md`.

## 0. Facts every draft may use (checked on the live site 2026-10-30, GET only)

| Fact | Where checked |
|---|---|
| 85 cities, 1,378 places with pages | home page counts |
| City pages carry an editorial rating labelled "Official Review" (the site's own, not a member's) | `/d/lisbon-portugal` |
| Free travel map: "Tap every country you have been to. Your map is a link you can share, no account needed." | `/map` |
| Review form: "Stars and a few lines, no account to start" | `/review/new`, nav |
| Buddy pages live (HTTP 200): `/buddies`, `/buddies/cruise`, `/buddies/backpacking`, `/buddies/road-trip` | direct GET |
| Cruise line pages live: carnival, celebrity, disney, holland-america, msc, norwegian, princess, royal-caribbean, viking, virgin-voyages under `/cruise-buddies/<line>` | links on `/buddies/cruise`, 3 spot checked 200 |
| Join free at 16, posting a trip is 18+, messaging opens only if you accept the request | cruise line page FAQ text |
| Traction, honestly: about 70 verified human visitors in 30 days, 1 signup, 0 member reviews (REVIEWS_CAMPAIGN baseline to 10-29) | internal KPI, not public |

Never quote a member, review, trip or match count that was not read from the site the same day. The buddies page shows a cruise sailing with "2 travelers on this exact sailing"; do not cite it, it has not been checked as real member activity.

Copy rules applied below: no dashes as punctuation, plain voice, no hype, no urgency, affiliation disclosed in every post, every link tagged, no text reused between communities. Placeholders in [brackets] must be replaced with the owner's real trip details; if he has no real trip for that group, skip the group rather than invent one.

Account notes before anything is posted:
* Facebook groups are posted from the owner's personal profile, not the RuinMyTrip Page (restricted until about 2026-11-08). Confirm the personal profile is not under any restriction first.
* Join each group and take part normally (answer a few questions) for about a week before the first post (ACQUISITION_FACEBOOK.md rule 2).
* Rules below were read from each group's About tab on 2026-10-30. Re-read them on the day; admins change rules.

---

## 1. Facebook groups

### 1a. Groups where posting is allowed (no promotion ban in the posted rules)

| # | Group | URL | Members (10/30) | Activity | Self promotion rule as posted | Links |
|---|---|---|---|---|---|---|
| 1 | TRAVEL COMPANION WANTED | https://www.facebook.com/groups/347239079558462/ | 211,947 | 216 posts last month | Two rules only: be kind, and posts must give the destination as a hashtag, the trip date and the kind of trip. No promotion rule. | Not restricted |
| 2 | FIND A TRAVEL BUDDY | https://www.facebook.com/groups/1536967100246672/ | 146,797 | 2,320 posts last month | No rules posted. | Not restricted |
| 3 | Solo Travelers Looking for Travel Partner | https://www.facebook.com/groups/1988507888053794/ | 275,572 | very active (90+ posts a day in search) | No rules list. Description: for finding travel buddies and sharing tourism, adventure and nature content, "Please avoid sharing any thing else." | Not restricted; keep it strictly about travel companions |
| 4 | Solo Cruise Forum By Passion For Cruises | https://www.facebook.com/groups/2172768439710926/ | 62,995 | 803 posts last month | One rule only: no hate speech. Note: the group is run by a UK cruise agency, so a site link may still be judged as competition. | Not restricted |
| 5 | Travelling Europe | https://www.facebook.com/groups/BackpackingEuropeGroup/ | 56,114 | 20 posts last month | Rules: be kind, no hate speech, respect privacy. No promotion rule. | Not restricted |
| 6 | Backpacking South-East Asia | https://www.facebook.com/groups/backpackingsoutheastasia/ | 29,837 | 232 posts last month | No rules posted. Description: travel photos, hostel info, where to go next. | Not restricted |
| 7 | Find a Cruise and Travel Buddy | https://www.facebook.com/groups/611542335662003/ | 5,123 | 7 posts last month (quiet) | No rules posted. Description invites members to share deals and helpful information. | Not restricted |

### 1b. Groups that allow promotion only with admin permission (ask first, post only on a yes)

| # | Group | URL | Members | Rule as posted |
|---|---|---|---|---|
| 8 | USA Travel & Road Trips | https://www.facebook.com/groups/1419315485706233/ | 1,771 | "No promotions or spam ... If you want to promote your business, then let the Admins know" |
| 9 | Royal Caribbean Cruise Friends & Family | https://www.facebook.com/groups/royalcaribbeancruisefriendsandfamily/ | 35,785 | "No advertising, selling or soliciting of any kind ... This includes self-promotion ... unless expressly permitted by an admin" |
| 10 | Cruise Friends & Family | https://www.facebook.com/groups/cruisefriendsandfamily/ | 11,832 | Same wording as #9: self promotion only if expressly permitted by an admin |

### 1c. Checked and ruled out (promotion banned, or paid only). Do not post.

Girls LOVE Travel (1,541,295; rule 4 bans advertising, referral, solicitation and promotion), TripMates Solo Travelers (668,073; "No Promotions or Ads"), FIND A TRAVEL BUDDY /groups/1018734283166921 (82,035; "No self-promotion or spamming"), Backpacking /groups/backpackingclan (41,778; "Self-promotion ... aren't allowed"), Travel Buddies /groups/3675050732600444 (20,982), Urban Travel Europe (12,120), Solo Cruise Buddies (1,107; "No business solicitation"), Solo cruiser group (22,781), Solo Cruisers (Carnival, Royal Caribbean...) (59,857; free solicitation banned, "pay per post" only, paid is skipped), Solo Cruisers And Cabin Sharing (28,920), Road Trip Buddies USA 2026 (14,216; no links of any kind), UK and Europe Road Trip Planning (77,985), Travel Buddies Network New Zealand (22,717), Cruises Fans MSC Friends (32,589; "No advertising"), Travel Buddies & Digital Nomads (58,464; run by a competing app, "Avoid self-promotion").

### 1d. Drafts (one per group, none reused)

UTM pattern: `utm_source=facebook&utm_campaign=launch&utm_content=<group-slug>`

**#1 TRAVEL COMPANION WANTED** (follows the group's required format: hashtag destination, date, kind of trip)
> #[Destination] [start date] to [end date], [kind of trip, e.g. food and walking, no tours]
>
> Looking for one or two people who will be around on any of those days for a meal or a day out. I am [one honest line about yourself].
>
> Full disclosure: I built a small free site for exactly this, where you post a city and dates and see who else overlaps. Nobody can message you until you accept them. It is new and still quiet, so replying here works just as well. My trip is on it if that is easier: https://ruinmytrip.com/buddies?utm_source=facebook&utm_campaign=launch&utm_content=fb-travel-companion-wanted

**#2 FIND A TRAVEL BUDDY**
> Question for people who have actually met up with someone from a group like this: what made you trust them enough to meet? A video call first, a public place, mutual friends?
>
> I ask because I built a free site where travelers post where and when they are going, and I made messaging open only after both sides accept. I am trying to work out what else would make people feel safe. It is new with very few members, so honest answers here help me more than signups: https://ruinmytrip.com/buddies?utm_source=facebook&utm_campaign=launch&utm_content=fb-find-a-travel-buddy

**#3 Solo Travelers Looking for Travel Partner** (description limits posts to travel content; keep it to companions)
> Going solo to [city] from [date] to [date]. Happy to share a hostel common room dinner, a hike or a day trip with anyone around then.
>
> I should say I made a free page where travelers list their city and dates and see who overlaps, so I am partly testing whether it helps. It is very new. Here it is if you want to add your dates there too: https://ruinmytrip.com/buddies?utm_source=facebook&utm_campaign=launch&utm_content=fb-solo-travelers-partner

**#4 Solo Cruise Forum By Passion For Cruises** (agency run; lead with the question, link last; if a moderator objects, do not argue)
> Solo cruisers: how far ahead do you try to find people on your sailing? I have seen people arrange a sail away drink or a shared shore day weeks before boarding, and others who would rather just turn up.
>
> Disclosure: I run a small free site with a page per cruise line where you post your ship and sail date and see who else is on it. Nobody can message you until you accept. It is early and most sailings have nobody on them yet, so I would genuinely like to know if solos would use this or prefer the roll call style: https://ruinmytrip.com/buddies/cruise?utm_source=facebook&utm_campaign=launch&utm_content=fb-solo-cruise-forum

**#5 Travelling Europe** (value first, a real tip, then disclosure)
> A small thing that saved me money in [city]: [one real, current tip from the owner's own trip, e.g. which day museums are free, checked on the official site that day].
>
> What is the one thing you wish someone had told you before your first trip there? I collect these on a free site I built, where travelers write what nearly ruined a trip so the next person avoids it. You can write one without an account. It is new and most places have no reviews yet: https://ruinmytrip.com/ruined?utm_source=facebook&utm_campaign=launch&utm_content=fb-travelling-europe

**#6 Backpacking South-East Asia**
> For people who have done the classic loop: which stop did you leave early, and why? Mine was [owner's real answer].
>
> Asking partly because I built a free review site that asks what went wrong rather than for a star rating, plus a map where you color in the countries you have been to and share it as a link, no account needed. It is new, so I would value a few honest answers here more than anything: https://ruinmytrip.com/map?utm_source=facebook&utm_campaign=launch&utm_content=fb-backpacking-sea

**#7 Find a Cruise and Travel Buddy** (small and quiet group; a plain, friendly intro fits)
> Hello all, new here. I am [one line about yourself] and looking at [ship or line] around [month].
>
> Since the group welcomes helpful things: I built a free page per cruise line where you post your ship and date and see who else is sailing. Messaging only opens when both people accept. It has very few people on it so far, so if it is useful, adding your sailing there helps the next person find you: https://ruinmytrip.com/cruise-buddies/[line]?utm_source=facebook&utm_campaign=launch&utm_content=fb-find-cruise-travel-buddy

**Admin request for #8, #9, #10** (sent by the owner as a private message to one admin per group; write each in his own words, do not paste the same text three times)
> Hi, I am a member of [group]. I built a free travel site (no fees for members; some hotel and tour links may be affiliate links, disclosed on the site) where people post their [cruise line / road trip] dates and find others on the same dates, with messaging only after both accept. Nothing is sold. Would you be OK with me posting about it once, with a link and a clear note that I built it? If not, no problem at all, I will not post it.

**Trip free alternates** (use these when the owner has no real upcoming trip; never fill the [brackets] with an invented one)

**#1 alternate (TRAVEL COMPANION WANTED)**: skip. The group requires a real destination and dates; without a trip there is nothing honest to post.

**#3 alternate (Solo Travelers Looking for Travel Partner)**
> For anyone who has found a travel partner online: did you agree on a budget and daily pace before meeting, or work it out on the road? I have seen trips go wrong over exactly that.
>
> Disclosure: I made a free page where travelers list a city and dates and see who overlaps, and messaging only opens once both people accept. It is new and quiet, so I am mainly asking what people actually check first: https://ruinmytrip.com/buddies?utm_source=facebook&utm_campaign=launch&utm_content=fb-solo-travelers-partner

**#5 alternate (Travelling Europe)**
> What is the one thing you wish someone had told you before your first trip to a European city you love? A closing day, a ticket you should have booked ahead, a neighborhood to skip?
>
> I ask because I built a free site where travelers write what nearly ruined a trip, so the next person can avoid it. You can write one without an account. It is new, most places have no member reviews yet, and I would be glad to read answers here even if nobody clicks: https://ruinmytrip.com/ruined?utm_source=facebook&utm_campaign=launch&utm_content=fb-travelling-europe

**#6 alternate (Backpacking South-East Asia)**
> For people who have done the classic loop: which stop did you leave early, and which one did you wish you had more days for?
>
> Partly asking for a free review site I built that asks what went wrong rather than only for stars. It also has a map where you color in the countries you have been to and share it as a link, no account needed. It is new, so honest answers here are worth more to me than visits: https://ruinmytrip.com/map?utm_source=facebook&utm_campaign=launch&utm_content=fb-backpacking-sea

**#7 alternate (Find a Cruise and Travel Buddy)**
> Hello all, new here. A question for people who found cabin mates or shore day company through a group like this: what did you agree on before sailing?
>
> Since the group welcomes helpful things: I built a free page per cruise line where you post your ship and date and see who else is sailing. Messaging only opens when both people accept. Very few sailings have anyone on them yet, so adding yours helps the next person find you: https://ruinmytrip.com/buddies/cruise?utm_source=facebook&utm_campaign=launch&utm_content=fb-find-cruise-travel-buddy

**Posts for the admin approval groups** (use only after that group's admin says yes in writing; each is written for its own group)

**#8 USA Travel & Road Trips** (after an admin yes)
> Thanks to the admins for letting me share this once. I built a free page for road trips: you post your route or start city and dates, and see who else is driving the same way, for company or to share fuel. Nobody can message you until you accept them, and posting a trip is 18 and over. It is new, so there are very few trips on it yet. If you try it, I would like to hear what is missing: https://ruinmytrip.com/buddies/road-trip?utm_source=facebook&utm_campaign=launch&utm_content=fb-usa-road-trips

**#9 Royal Caribbean Cruise Friends & Family** (after an admin yes)
> With the admins' OK: I made a free page for Royal Caribbean sailings where you add your ship and sail date and see who else on the site is on the same one, for a sail away drink or a shared port day. Messaging opens only after both people accept. I built it, it is new, and most sailings have nobody on them yet, so this is as much a request for feedback as anything: https://ruinmytrip.com/cruise-buddies/royal-caribbean?utm_source=facebook&utm_campaign=launch&utm_content=fb-rci-friends-family

**#10 Cruise Friends & Family** (after an admin yes)
> Posting with admin permission. For anyone who likes meeting people before boarding: I built a free page per cruise line where you post your ship and date and see who else is sailing. You choose who can message you. It is early days with only a handful of people, and I would welcome blunt feedback from regular cruisers here: https://ruinmytrip.com/buddies/cruise?utm_source=facebook&utm_campaign=launch&utm_content=fb-cruise-friends-family

Post only after a written yes. Then use a fresh draft written for that group (link for #8: `https://ruinmytrip.com/buddies/road-trip?utm_source=facebook&utm_campaign=launch&utm_content=fb-usa-road-trips`; #9: `https://ruinmytrip.com/cruise-buddies/royal-caribbean?utm_source=facebook&utm_campaign=launch&utm_content=fb-rci-friends-family`; #10: `https://ruinmytrip.com/buddies/cruise?utm_source=facebook&utm_campaign=launch&utm_content=fb-cruise-friends-family`).

---

## 2. Indie Hackers

Norms (source: Indie Hackers has no single published self promotion policy; group owners set up to five posting guidelines per group, see https://www.indiehackers.com/product/indie-hackers/added-support-for-group-posting-guidelines--MHDC04726tqs6fDr-W5). In practice the posts that do well tell the build story, show real numbers including small ones, and ask a specific question. Post in the main feed or the relevant group, read that group's guidelines first, and reply to every comment that day. Verdict: **go** (LAUNCH_CAMPAIGN_20261029 week 1 item 4; note LAUNCH_LISTINGS.md suggested waiting for about 50 reviews, the newer campaign plan supersedes it).

Draft (posted by the founder from his own account; no names):

> **Title:** I built a travel site where the review form asks what went wrong. 30 days in: 1 signup, 0 reviews. What would you change?
>
> I kept noticing that the most useful thing I read before any trip was someone saying what went badly. Review sites tend to bury that under five star averages. So I built RuinMyTrip.
>
> What it does today:
> * A page for each of 85 cities and about 1,378 places. City pages carry a rating we write ourselves, clearly labelled "Official Review", so a page is not empty before members arrive.
> * A review form that starts with stars and a few lines and needs no account until the end.
> * A free map where you tap the countries you have been to and get a link to share. No account needed.
> * Travel buddies by overlapping dates, including a page per cruise line where you post your ship and sail date. Nobody can message you until you accept, and posting a trip is 18 and over.
>
> Honest numbers for the last 30 days: about 70 verified human visitors, 1 signup, 0 member reviews. Search gave a few hundred impressions and a handful of clicks. The problem right now is reach, not the form, but I would rather hear that from people who look at it cold.
>
> How it is run: I make the product calls, and day to day work like code changes, content checks and moderation is done with an AI agent (Claude), which I review before anything ships.
>
> What I would love feedback on:
> 1. In the first minute, is it clear what the site is for?
> 2. Would you write a two line "what went wrong" review? If not, what would make you?
> 3. Is the travel buddy side a separate product that should be split off?
>
> https://ruinmytrip.com/?utm_source=indiehackers&utm_campaign=launch&utm_content=founder-post

(Before posting, re-read the visitor and signup numbers from KPI.md that morning and update them; do not post stale figures.)

---

## 3. Cruise Critic

**How roll calls work.** Each line has a Roll Calls forum, subdivided by ship (for example Royal Caribbean Roll Calls https://boards.cruisecritic.com/forum/21-royal-caribbean-roll-calls/, Norwegian https://boards.cruisecritic.com/forum/66-norwegian-cruise-line-roll-calls/, Holland America https://boards.cruisecritic.com/forum/52-holland-america-roll-calls/). One thread per ship and sail date. Passengers join the thread, introduce themselves, and arrange meet and greets, shared tours and cabin crawls. Help pages: https://boards.cruisecritic.com/topic/2997081-how-to-join-your-roll-call/ and https://boards.cruisecritic.com/topic/2999013-basic-information-on-using-roll-call-to-ask-and-to-reply-to-questions/

**Rules on links and promotion** (Community Guidelines, https://boards.cruisecritic.com/guidelines/ ; the page blocks automated reads, wording below is from search excerpts of that page and must be read in a browser before posting):
* Cruise Critic prohibits "blatant advertising, commercial sales and solicitation posts on all of our message boards", and may remove anything "too promotional in tone", including bylines, user names and avatars.
* Solicitation includes "the referring to or posting of one's own Web site address".
* You may not post a link to a Facebook roll call to recruit members away from Cruise Critic; the roll call is expected to stay on Cruise Critic.
* The roll call thread for your line, ship and date is the only place to post personal requests to share private tours or transport.

**Verdict: links to RuinMyTrip never.** Naming the site or putting it in a signature counts as posting one's own website. The only legitimate use is the owner taking part in roll calls for sailings he is actually booked on, as a passenger. That builds no traffic directly, so it is low priority. Requires a Cruise Critic account the owner creates himself.

**Three reply drafts (no link, no site name; only on a sailing the owner is really on):**

A. Introduction
> Hi everyone, [first name or handle] here, sailing with [who] in [cabin area if you like]. First time on [ship]. We are keen on a sail away meet up and maybe sharing a tour in [port]. Has anyone booked anything there yet?

B. Useful tip for the thread
> For anyone stopping in [port]: [one specific, current fact checked that day on the port authority or official tour site, e.g. tender times, where the taxi rank is, which day a market runs]. Happy to share the link to the official page if anyone wants it.

C. Organizing a shared tour (the roll call is the allowed place for this)
> We are thinking of a private tour in [port] for [what], roughly [hours]. A van takes up to [n], which would bring the cost per person down a lot. Anyone interested? Reply here and we can sort details in the thread.

**Five sailings in the next 90 days on lines that have a `/cruise-buddies/<line>` page** (roll call threads found in the forums on 2026-10-30; open each thread to confirm it is active before relying on it):

| Sailing | Line page on RuinMyTrip | Roll call forum |
|---|---|---|
| Nieuw Amsterdam, 29 Nov to 11 Dec 2026, 12 day Eastern Caribbean | /cruise-buddies/holland-america | Holland America Roll Calls |
| Voyager of the Seas, 4 to 11 Dec 2026, Cabo, Mazatlan, Puerto Vallarta | /cruise-buddies/royal-caribbean | https://boards.cruisecritic.com/forum/188-voyager-of-the-seas-roll-calls/ |
| Nieuw Statendam, 5 to 20 Dec 2026, Transatlantic | /cruise-buddies/holland-america | https://boards.cruisecritic.com/forum/824-nieuw-statendam-roll-calls/ |
| Norwegian Jewel, 8 to 18 Dec 2026, Caribbean from Miami | /cruise-buddies/norwegian | Norwegian Cruise Line Roll Calls |
| Ovation of the Seas, 21 Dec 2026 holiday cruise | /cruise-buddies/royal-caribbean | https://boards.cruisecritic.com/forum/767-ovation-of-the-seas-roll-calls/ |

Also seen: Norwegian Joy 14 to 18 Dec 2026 roll call, and a Facebook group "Norwegian Getaway Nov 16-23 2026" (82 members, private). Do not post RuinMyTrip in either.

---

## 4. Other free channels

| Channel | Rule summary | Source | Verdict |
|---|---|---|---|
| r/SideProject | Exists for sharing side projects and getting feedback; self promotion is the most common post type. Official sidebar text could not be read (Reddit blocks automated reads); recheck it. | https://thehiveindex.com/communities/side-project/ , https://gummysearch.com/r/SideProject/ | **not yet**: needs an established personal Reddit account (only u/TrustMyRecord exists, removed 3 times) |
| r/solotravel | Links to blogs, surveys and self promotional content removed from the main feed, allowed only in the weekly General Chatter thread; AI written itineraries banned | mirror https://r-weld.vercel.app/r/solotravel (via REVIEWS_CAMPAIGN), weekly thread example https://nyc1.lr.ggtyler.dev/r/solotravel/comments/1fhprrl/rsolotravel_the_weekly_common_room_general | **not yet**: weekly thread only, human written, once an account exists |
| r/travelpartners | Posts must put start date, end date and destination in the title; no confirmed rule on site links | https://axebps-redlib.hf.space/r/travelpartners | **not yet**: only for the owner's own real trip, no link unless asked |
| r/travel | Self promotion not allowed | REVIEWS_CAMPAIGN_20261030 section 3 | **never** for links |
| r/Cruise | Reddit's spam definition covers any blatant advertising; follows Reddit self promotion guidelines | https://axebps-redlib.hf.space/r/Cruise/wiki/rules | **never** for links |
| r/backpacking | "a community of users, not a platform for advertisement, self promotion, surveys, or blogspam" | https://lr.in.psf.lt/r/backpacking/duplicates/ausd2h | **never** for links |
| Tripadvisor forums | Links only if relevant and not promotional; solicitation and self promotion banned; accounts disabled for it | https://www.tripadvisor.com.au/Trust-llmsjBtituuk , https://www.phocuswire.com/TripAdvisor-blacklists-a-hotel-professional-from-its-forums-raising-questions | **never** |
| Rick Steves forum | Moderators remove posts promoting blogs or guides | REVIEWS_CAMPAIGN_20261030 section 3 | **never** |
| FlyerTalk | Promoting a commercial blog goes to moderator review | https://www.flyertalk.com/forum/29002463-post2.html | **never** |
| Fodor's forums | Non commercial forum; promotion removed | https://www.fodors.com/community/europe/if-fodors-is-a-non-commercial-forum-869615-printthread/ | **never** |
| Cruise Critic | See section 3 | https://boards.cruisecritic.com/guidelines/ | **never** for links |
| Travel Discords | No specific server's rules were verified today. Many have a #self-promo channel; rules differ per server | none verified | **not yet**: owner picks one server he uses, reads its rules, posts only in its promo channel |
| Hacker News, Show HN | Must be something people can try, ideally "without barriers such as signups or emails"; no landing pages; maker posts and answers comments; no asking friends to upvote | https://news.ycombinator.com/showhn.html | **not yet**: the site qualifies technically (review form, map and city pages work without an account), but content is thin. LAUNCH_LISTINGS.md says wait for about 50 real reviews. Draft title and text are ready there. Weekday, 8 to 10 AM Pacific. |
| Product Hunt | Maker posts from a personal account; launch at 12:01 AM Pacific, Tuesday to Thursday | LAUNCH_LISTINGS.md (producthunt.com/launch blocks automated reads) | **not yet**: one launch day only; wait for 100+ reviews and a few trips |
| Launching Next | "Submitting to Launching Next is free"; a $99 upgrade only speeds review | https://www.launchingnext.com/submit/ | **go** (free tier only) |
| Microlaunch | Free launch listing with a paid feature option | https://microlaunch.net | **go** if the free option still needs no backlink; check on the form |
| Peerlist Launchpad | Weekly launch for products, needs a Peerlist profile | https://peerlist.io/launchpad | **go** if the owner is fine creating a Peerlist profile himself |
| StartupBase | Free launch, offers a backlink | https://startupbase.io | **go** if the free path needs no reciprocal link |
| TinyLaunch | Launch is free; the dofollow backlink is granted only if you embed their badge | https://www.tinylaunch.com | **go** without the badge (listing still works); skip if a badge is required to list |
| Twelve Tools | Free submission flow includes a badge step | https://www.twelve.tools/submit-your-tool | **not yet**: skip if the badge is mandatory (same reason Fazier was skipped) |
| SaaSHub, AlternativeTo, Uneed | Already submitted | REVIEWS_CAMPAIGN section 8 | done |
| BetaList | Paid to skip queue | | skipped (paid) |
| Fazier | Needs a backlink | | skipped |

**Directory copy:** use the tagline, short description and screenshots in LAUNCH_LISTINGS.md, with these links (one per directory):
`https://ruinmytrip.com/?utm_source=directory&utm_campaign=launch&utm_content=launchingnext` (swap `launchingnext` for `microlaunch`, `peerlist`, `startupbase`, `tinylaunch`). If a directory has a "maker comment" box, use this, not the Indie Hackers text:
> I built this. RuinMyTrip has a page for 85 cities and about 1,378 places, a review form that asks what nearly ruined the trip and needs no account to start, a shareable country map, and travel buddies matched by overlapping dates, including by cruise line. It is new and has very few members, which is why feedback is the most useful thing right now.

**Draft for r/SideProject (only once an established personal account exists; rewrite in your own words, Reddit removes AI written text):**
> Title: Feedback wanted: travel reviews that start from "what went wrong"
>
> I made this as a side project. The bet is that the most useful travel review is the one that says what nearly ruined the trip, so the form asks that first and you can write one without an account. There is also a country map you can share and a way to find people going to the same city on the same dates.
>
> It has almost no members yet. What would stop you from writing a two line review? https://ruinmytrip.com/?utm_source=reddit&utm_campaign=launch&utm_content=sideproject

---

## 5. Two week calendar (Mon 2 Nov to Sun 15 Nov 2026), one post per community

| Date | Channel | Draft | Notes |
|---|---|---|---|
| Mon 2 Nov | Facebook | none | Join groups #1 to #7 from the personal profile; comment helpfully on other posts all week. No links. |
| Tue 3 Nov | Indie Hackers | Section 2 | Post in the morning Pacific, refresh numbers from KPI.md first, reply to comments that day |
| Wed 4 Nov | Launching Next | Directory copy | Free tier |
| Thu 5 Nov | Microlaunch | Directory copy | Free only |
| Fri 6 Nov | StartupBase | Directory copy | Skip if reciprocal link needed |
| Sat 7 Nov | Facebook admins | Admin request #8, #9, #10 | One message per admin, each reworded |
| Mon 9 Nov | FB #1 TRAVEL COMPANION WANTED | #1 | Only with a real trip; use the group's hashtag format |
| Tue 10 Nov | FB #4 Solo Cruise Forum | #4 | |
| Wed 11 Nov | FB #5 Travelling Europe | #5 | Needs one real, checked tip |
| Thu 12 Nov | FB #2 FIND A TRAVEL BUDDY | #2 | |
| Fri 13 Nov | FB #6 Backpacking South-East Asia | #6 | |
| Sat 14 Nov | TinyLaunch or Peerlist | Directory copy | Whichever needs no badge |
| Sun 15 Nov | FB #3 or #7 | #3 or #7 | Pick one; the other goes to week 3. Admin groups #8 to #10 only if a written yes came back |

If a moderator removes a post: note the reason in `docs/ACQUISITION_EXPERIMENTS.md`, do not repost, do not argue. Drop any channel that sends zero humans in two weeks.

### Tracking table (fill clicks from the Tagged links table in KPI.md)

| UTM link | Date posted | Humans (KPI) | Signups | Notes |
|---|---|---|---|---|
| https://ruinmytrip.com/?utm_source=indiehackers&utm_campaign=launch&utm_content=founder-post | | | | |
| https://ruinmytrip.com/?utm_source=directory&utm_campaign=launch&utm_content=launchingnext | | | | |
| https://ruinmytrip.com/?utm_source=directory&utm_campaign=launch&utm_content=microlaunch | | | | |
| https://ruinmytrip.com/?utm_source=directory&utm_campaign=launch&utm_content=startupbase | | | | |
| https://ruinmytrip.com/?utm_source=directory&utm_campaign=launch&utm_content=tinylaunch | | | | |
| https://ruinmytrip.com/?utm_source=directory&utm_campaign=launch&utm_content=peerlist | | | | |
| https://ruinmytrip.com/buddies?utm_source=facebook&utm_campaign=launch&utm_content=fb-travel-companion-wanted | | | | |
| https://ruinmytrip.com/buddies/cruise?utm_source=facebook&utm_campaign=launch&utm_content=fb-solo-cruise-forum | | | | |
| https://ruinmytrip.com/ruined?utm_source=facebook&utm_campaign=launch&utm_content=fb-travelling-europe | | | | |
| https://ruinmytrip.com/buddies?utm_source=facebook&utm_campaign=launch&utm_content=fb-find-a-travel-buddy | | | | |
| https://ruinmytrip.com/map?utm_source=facebook&utm_campaign=launch&utm_content=fb-backpacking-sea | | | | |
| https://ruinmytrip.com/buddies?utm_source=facebook&utm_campaign=launch&utm_content=fb-solo-travelers-partner | | | | |
| https://ruinmytrip.com/cruise-buddies/[line]?utm_source=facebook&utm_campaign=launch&utm_content=fb-find-cruise-travel-buddy | | | | |
| https://ruinmytrip.com/buddies/road-trip?utm_source=facebook&utm_campaign=launch&utm_content=fb-usa-road-trips | | | | admin yes needed |
| https://ruinmytrip.com/cruise-buddies/royal-caribbean?utm_source=facebook&utm_campaign=launch&utm_content=fb-rci-friends-family | | | | admin yes needed |
| https://ruinmytrip.com/buddies/cruise?utm_source=facebook&utm_campaign=launch&utm_content=fb-cruise-friends-family | | | | admin yes needed |

Tracking check: `facebook`, `indiehackers`, `reddit` and `directory` are all in `RMT_ACQ_SOURCES` (`app/acquisition.php`, checked 10/30), so each row is credited; `utm_content` tells the groups and directories apart.

---

## 6. Launch messaging (use across every channel)

**One line:** RuinMyTrip is where travelers say what actually went wrong, so the next person does not repeat it, and where you can find people going to the same place on the same dates.

**Three pillars, in this order**
1. *Honest reviews.* Start from what nearly ruined the trip: the tourist trap, the surprise fee, the queue that was not worth it. Critical reviews are welcome and nobody can pay for a rating. Our own editorial ratings are always labelled "Official Review" and never mixed into traveler scores.
2. *Travel companions.* Post a city or a sailing with your dates and see who overlaps. Nobody can message you until you accept, posting a trip is 18 and over, and meetups follow the safety page.
3. *Share where you have been.* A free country map you can share as a link, and short trip notes, no account needed to start.

**Proof points we can use (true on 2026-10-30, re-check before each post):** 85 city pages and about 1,378 place pages; review form and map work without an account; a page per cruise line for 10 lines.

**Never say:** member counts, review counts, "thousands of travelers", "join the community everyone is talking about", testimonials, quotes from members, or any trip or match we did not see on the site that day. Do not describe a personal trip that did not happen. Say plainly that the site is new.

**Calls to action (pick one per post):** write a two line review of a place that disappointed you; color in your map; post your dates and see who overlaps.

**Weekly rhythm after the two week calendar:** one new community or directory per weekday at most, one Facebook post per group per month at most, every link tagged, drop channels that send zero humans in two weeks (KPI.md Tagged links table).

## 7. Hosts & Locals recruitment (APPROVED by Nima 2026-10-31, nothing posted yet)

Schedule: 7.1 and 7.2 from Mon Nov 9 (Facebook restriction lifts about Nov 8), one group per day, featured cities first. 7.3 right after the Indie Hackers founder post (2.x, Nov 3), which itself still needs approval. 7.4 waits for an established Reddit account.

Rules: same as section 6. Say the site is new. No member counts. Hosting is free, identities are not checked, and the host decides every request. Link tags: `?utm_source=<channel>&utm_campaign=hosts_locals`.

**7.1 Expat and newcomer groups in the eight featured cities (Facebook groups that allow links, one per city per month)**
> Anyone here happy to meet a traveler now and then? RuinMyTrip just opened Hosts & Locals: you list yourself as a local in your city and say what you would be up for, a coffee, a walk around your neighbourhood, food tips. Travelers ask, you say yes or no, and nobody can message you until you say yes. Free, and only your city shows. https://ruinmytrip.com/hosts-locals

**7.2 Couchsurfing style hosting communities (where self promotion is allowed)**
> For anyone who still likes hosting travelers for free: RuinMyTrip opened free hosting. No fees and no paid listings. A moderator reads every host profile before it goes up, you set your own nights and house rules, and you accept or decline every request. It never stores your address: you agree where to meet in messages after a yes. Identities are not checked, so references matter. https://ruinmytrip.com/hosts-locals/become-a-host

**7.3 Indie Hackers update (after the Nov 3 founder post, only if that post was approved)**
> Update: RuinMyTrip now has Hosts & Locals. One account can be a traveler, a local and a host. Locals and stays both run on a request the other side can refuse, messages open only after a yes, and references can only be written after a real accepted connection. Built on the existing travel buddy and messaging code rather than a new system. https://ruinmytrip.com/hosts-locals

**7.4 Reddit (only from an established personal account, only in subs that allow it, e.g. weekly self promotion threads)**
> Built a free way to meet locals and stay with hosts, with the guardrails I wished other sites had: request based, no messages until both agree, no addresses stored, references only after a real connection. Feedback welcome. https://ruinmytrip.com/hosts-locals
