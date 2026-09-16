# Acquisition packages, ready to post

Everything here is written to be copied and pasted by Nima. **Nothing has been posted, and nothing
here posts itself.** Written 2026-09-15, against the product as it exists today.

## Before anything: the one thing to check yourself

**I could not read the subreddit rules from here.** Reddit answers 403 to every unauthenticated
request from this machine, and I will not tell you what a community's rules say without having read
them. Before posting, open the sidebar of the target subreddit and check two things:

1. whether self promotion or "my project" posts are allowed at all, and on which day if there is a
   thread for it
2. whether links are allowed in the post body or only in a comment

Every draft below **discloses that you built it**, because an undisclosed link from an account that
has never posted there is the one thing that ends a channel permanently.

## What is being linked to, and why

The landing page is a **city page**, never the homepage. Somebody reading about Miami in December
should land on Miami, see the community block, the composer and the Follow control, and be one tap
from posting their dates. The homepage explains; the city page converts.

The tracked link shape:

    https://ruinmytrip.com/d/{city-slug}?utm_source={channel}&utm_medium={post|comment}&utm_campaign={label}

`utm_source` must be one of: `reddit facebook instagram tiktok x youtube linkedin pinterest
whatsapp telegram discord search referral email direct other`. Anything else is recorded as
`other`. `utm_medium` must be one of: `post comment reply bio story group dm social organic email
referral other`. The campaign label is lowercased and trimmed to 40 characters.

Read the result at `/admin/funnel` under "Where people came from", or
`/cron/funnel?key=CRON_KEY&days=7` under `acquisition`.

---

# PRIORITY 4 — Reddit

## The wedge

**Miami, 1 to 7 December 2026, Art Week.** Chosen in `docs/FIRST_COHORT.md` and still correct
eleven weeks out, which is when that week actually gets planned. The city page holds 110 real
places and the trip form pre fills the dates.

## Recommended subreddits, in order

1. **r/solotravel** — the single best fit. The question "how do I find people going the same week"
   is asked there constantly and this is a real answer to it. Large and strictly moderated.
2. **r/Miami** — local, tolerant of Art Week threads in season, small enough that a genuine post is
   read rather than buried.
3. **r/travel** — largest reach, least tolerance for anything that reads as promotion. Go here
   third, if at all, and only after the first two have gone well.

Do not post the same text in two subreddits. Do not post to more than one in a day.

## Post 1, r/solotravel

**Title**

    Anyone else going to Miami for Art Week (1 to 7 Dec)? Trying to work out if the satellite fairs are worth the extra days

**Body**

    I am going to Miami 1 to 7 December for Art Week and I am stuck on the same thing every year:
    whether the satellite fairs justify staying past the main fair days, or whether three days is
    genuinely enough.

    If you have done it, I would take any opinion on that.

    Second thing, and I will be upfront that it is mine so delete this if it breaks a rule: I got
    tired of not knowing who else was going to be in a city the same week as me, so I built a small
    site where you put in your dates and see other travelers whose dates overlap yours. It is new
    and quiet, so there is not much there yet, which is exactly why I am asking here rather than
    pretending otherwise. My Miami dates are on it if anyone is around the same week.

    https://ruinmytrip.com/d/miami-usa?utm_source=reddit&utm_medium=post&utm_campaign=miami-art-week

**If links in the body are not allowed:** post the first two paragraphs alone, then add the third
as your own first comment with `utm_medium=comment`.

## Post 2, r/Miami

**Title**

    Art Week 1 to 7 December: which days are actually worth it if you are not in the industry?

**Body**

    Coming for Art Week and trying to plan around the crowds rather than into them. If you live
    there: which days are worth being in Wynwood and the Design District, and which are better
    spent anywhere else in the city?

    Full disclosure, I built a thing for exactly this problem: put your dates in and see who else
    is in the city that week. Mine are 1 to 7 December if anyone is around.

    https://ruinmytrip.com/d/miami-usa?utm_source=reddit&utm_medium=post&utm_campaign=miami-art-week

## Comment reply, for any existing thread

Use this when somebody is already asking about meeting people in a city. Do not paste it into
threads about anything else.

    If it is any use, I put my dates on ruinmytrip.com and it shows other travelers whose dates
    overlap in the same city. I built it, so take that as you will, and it is early and quiet. But
    it does answer the "who else is around that week" question without a group chat.
    https://ruinmytrip.com/d/{city-slug}?utm_source=reddit&utm_medium=comment&utm_campaign={city}

## What we want them to do, and what success is

**Land on `/d/miami-usa`, join, and post a trip with dates inside 1 to 7 December.**

* **Minimum success:** 1 signup traceable to `utm_source=reddit`. That is the first proof a channel
  can produce a human.
* **Real success:** 3 or more signups and at least 2 trips with dates, because two overlapping
  trips in one city is the first time this product has ever done the thing it exists for.
* **Failure worth knowing:** clicks with no signups tells us the city page does not convert, which
  is a different problem from having no traffic, and the dashboard will separate them.

---

# PRIORITY 5 — Facebook groups

Groups where the question is native. Search each name, join, read for a day, then post. **Never
post to more than one group per day, and never the same text twice.**

* Solo travel: "Solo Travel Society", "Girls LOVE Travel" (read the rules carefully, it is heavily
  moderated), "Solo Female Travelers"
* Destination: "Bangkok Expats", "Thailand Backpackers", "Japan Travel Tips", "Bali Travel Community"
* Nomad: "Digital Nomads Around the World", "Female Digital Nomads"

## Template A, the honest builder post

    I am building a small thing for a problem I kept having: you book a trip, and you have no idea
    who else will be in the same city that week.

    It is a page per city where you put your dates and see whose overlap. No app, free, and pretty
    empty so far, which is why I am asking here rather than announcing anything.

    If you are going to {CITY} in the next few months I would genuinely like to know whether this
    is useful or whether it is a solution to a problem only I have.

    https://ruinmytrip.com/d/{slug}?utm_source=facebook&utm_medium=group&utm_campaign={city}-builder

## Template B, answering a date question

    If it helps, I have my dates for {CITY} on ruinmytrip.com, which shows other travelers whose
    dates overlap. I built it so take it with the usual pinch of salt, but it is free and it
    answers exactly this.
    https://ruinmytrip.com/d/{slug}?utm_source=facebook&utm_medium=comment&utm_campaign={city}

## Template C, the question first post

    Going to {CITY} {MONTH}. For those who have been: {ONE REAL SPECIFIC QUESTION, e.g. which
    neighbourhood you would stay in a second time and which you would not}.

    Asking because I am putting my own dates up on a site I built for finding people travelling the
    same week, and I would rather arrive with a plan than a list of tabs.

    https://ruinmytrip.com/d/{slug}?utm_source=facebook&utm_medium=group&utm_campaign={city}-question

## Template D, for a nomad group

    Question for people who move around a lot: how do you find out whether anyone you would want to
    meet is in the same city as you that month?

    I built a small thing for it, which is a page per city where you put your dates and see whose
    overlap. Curious whether people solve this some other way already.

    https://ruinmytrip.com/d/{slug}?utm_source=facebook&utm_medium=group&utm_campaign=nomad

---

# PRIORITY 6 — The first thirty days of content

Real problems, real destinations, no invented travelers and no invented numbers. Each line is
ready to schedule. Every link takes `?utm_source={channel}&utm_medium={medium}&utm_campaign={label}`.

| # | Hook | Caption or script | Topic | CTA | Channel | Landing | Campaign |
|---|---|---|---|---|---|---|---|
| 1 | "You booked Bangkok. Who else is going that week?" | The one thing no booking site tells you is who else will be there. Put your dates in, see whose overlap. | Bangkok | Post your dates | TikTok, Reels | /d/bangkok-thailand | bangkok-dates |
| 2 | "Solo does not have to mean alone" | Solo travel is the best way to travel and the worst way to eat dinner. Here is how to find people going the same week. | Solo travel | Find travelers | TikTok, Reels | /travelers | solo-alone |
| 3 | "Art Week is 1 to 7 December" | Miami in December is three days of fairs and four days of everything else. Which days are worth it? | Miami | Ask the community | Reddit, X | /d/miami-usa | miami-art-week |
| 4 | "Which Tokyo neighbourhood, honestly?" | Shinjuku, Shibuya or somewhere a local would actually pick. Ask people who have been. | Tokyo | Ask a question | Reddit, Facebook | /d/tokyo-japan | tokyo-where |
| 5 | "The mistake everyone makes in Amsterdam" | It is not the tax. It is booking the three things that need booking two days before you arrive. | Amsterdam | Read the page | X, Reels | /d/amsterdam-netherlands | amsterdam-mistake |
| 6 | "Would you travel here alone?" | A city, three facts, one question. Let people argue in the replies. | Any city | Follow the city | X, TikTok | /d/{slug} | alone-poll |
| 7 | "Nobody tells you about the Lisbon hills" | Two minutes on what a map does not show you about a city. | Lisbon | Follow Lisbon | Reels, TikTok | /d/lisbon-portugal | lisbon-hills |
| 8 | "Going to Bali in {month}?" | Dates first, itinerary second. Find out who else is there before you plan around nobody. | Bali | Post your dates | Facebook groups | /d/seminyak-indonesia | bali-dates |
| 9 | "What is the worst travel advice you were given?" | A question post, no link in the body, link in the first comment if the rules allow. | General | Join the conversation | Reddit | /talk | worst-advice |
| 10 | "Three days in Marrakech: enough or not?" | Ask the people who have been rather than the people who write listicles. | Marrakech | Ask the community | Facebook, Reddit | /d/marrakech-morocco | marrakech-days |
| 11 | "Zanzibar now needs an e-visa" | One real practical fact, then the page that has the rest. | Zanzibar | Read the page | X | /d/zanzibar-tanzania | zanzibar-visa |
| 12 | "How do you meet people when you travel alone?" | Genuine question, answer in the replies, mention the site only if somebody asks. | Solo travel | None | Reddit | /travelers | solo-how |
| 13 | "The queue you should skip in Rome" | One specific piece of advice from a real place page. | Rome | Browse places | Reels | /d/rome-italy | rome-queue |
| 14 | "Same week, same city, never met" | The pitch in eight words, over a map. | Product | Find travelers | TikTok | / | same-week |
| 15 | "Berlin in winter: worth it?" | A real debate with two defensible sides. | Berlin | Follow Berlin | X, Facebook | /d/berlin-germany | berlin-winter |
| 16 | "What I got wrong about Hoi An" | One honest correction beats five recommendations. | Hoi An | Read the page | Reels | /d/hoi-an-vietnam | hoian-wrong |
| 17 | "Post your dates, see who overlaps" | Fifteen second screen recording of posting a trip and landing on the overlap page. | Product | Post your dates | TikTok, Reels | /trip/new | how-it-works |
| 18 | "Oaxaca in {month}" | Why the month matters more here than almost anywhere. | Oaxaca | Follow Oaxaca | Facebook | /d/oaxaca-mexico | oaxaca-month |
| 19 | "Would you share a taxi with a stranger?" | A real safety conversation, not a pitch. Where the meetup rules come from. | Safety | Read safety | Reddit | /safety | safety-taxi |
| 20 | "Banff without a car" | A specific answerable question a lot of people have. | Banff | Ask the community | Facebook | /d/banff-canada | banff-car |
| 21 | "The best meal I had cost four dollars" | Ask for theirs. Real replies become real content on the site. | Food | Join the conversation | X, Reddit | /talk | four-dollars |
| 22 | "Milan needs booking, not wandering" | Two things that must be booked, one that must not. | Milan | Read the page | Reels | /d/milan-italy | milan-book |
| 23 | "Anyone in {city} in {month}?" | The plainest possible post. Repeat monthly with a different city. | Rolling | Post your dates | Facebook groups | /d/{slug} | monthly-ask |
| 24 | "First time in Thailand: what would you do differently?" | Question first. The answers are the content. | Thailand | Ask the community | Reddit | /d/bangkok-thailand | thailand-again |
| 25 | "Travel buddy or travel alone?" | A genuine two sided question for a poll. | Solo travel | Find travelers | X, Instagram | /travelers | buddy-or-alone |
| 26 | "What a place page looks like when nobody is selling you anything" | Show a real place page with the attribution line. | Product | Browse places | Reels | /d/lisbon-portugal/places | no-selling |
| 27 | "Three days or five in Amsterdam?" | Short, answerable, argued about. | Amsterdam | Ask the community | Facebook | /d/amsterdam-netherlands | amsterdam-days |
| 28 | "The thing I always forget to book" | Invite the same confession from everybody else. | General | Join the conversation | X | /talk | always-forget |
| 29 | "Going somewhere in {month}? Put the dates up" | End of month push, one city per region. | Rolling | Post your dates | All | /trip/new | month-end |
| 30 | "What did this site get wrong?" | Ask for criticism in public and answer it in public. | Product | Give feedback | Reddit | /contact | what-wrong |

**Rules that apply to all thirty:** never invent a traveler, a question, a review or a number;
never post the same text on two channels; where a post is a question, it has to be a question you
actually want answered.

---

# The exact human action needed

1. Check the subreddit rules in the sidebar, as above.
2. Post **one** of the two Reddit drafts, from your own account.
3. Tell me you posted it.

Everything downstream is built, instrumented and verified: the link is tracked, the landing page
explains itself to a stranger, the signup path works, and the dashboard will attribute the result
to `reddit` within seconds of the first click.
