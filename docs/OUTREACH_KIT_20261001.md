# First 50 travelers: execution kit (1 October 2026)

Three ecosystems only. No new destination campaigns until one of these has 15 real travelers.

A **traveler** here means a real person with a confirmed match alert or a public trip in one of the
three windows. Accounts with neither do not count.

## 1. Targets

| Ecosystem | Page | Window | Target | Why this share |
|---|---|---|---|---|
| Lisbon, Web Summit | /e/web-summit-lisbon | 9 to 12 Nov | **20** | About 70,000 attendees, many flying in alone, the "who else is going alone" problem is sharpest here, and Lisbon has the biggest reachable communities (Meetup 25,884, nomad Facebook groups, event newsletters). |
| Chiang Mai, Yi Peng | /e/yi-peng-chiang-mai | full moon Tue 24 Nov | **18** | Huge traveler interest (r/ThailandTourism 984,888) and 54 days of runway. Backpacker heavy, people already look for others to share a lantern night and transport. |
| Oaxaca, Day of the Dead | /e/day-of-the-dead-oaxaca | 31 Oct to 2 Nov | **12** | Only 30 days left and fewer open channels (r/Oaxaca has no promo thread yet). Start first because it closes first. |

Deadline: Oaxaca by 31 Oct, the other two by 20 Nov.

## 2. How much outreach that takes (assumptions, to be replaced by measured rates after week 1)

| Step | Assumed rate | Source of the assumption |
|---|---|---|
| Qualified visit to alert submitted | 5% | Warm, event specific traffic landing on a form prefilled with the dates. Unmeasured. |
| Alert submitted to confirmed | 65% | Double opt in norms. Unmeasured. |
| Visit to traveler | about 3.2% | product of the two |

50 travelers therefore need about **1,550 qualified visits**: about 620 Lisbon, 560 Chiang Mai, 380 Oaxaca.

| Channel | Visits per action (assumed) | Actions needed |
|---|---|---|
| Facebook group post, admin approved | 40 to 120 | 15 to 20 posts across the three |
| Reddit post or thread comment where allowed | 30 to 200 | 5 to 8 |
| Instagram post or story plus hashtag/location reach | 5 to 30 | 20 to 30 posts and stories |
| Micro creator mention | 50 to 300 | 3 to 6 creators |
| Newsletter feature | 50 to 400 | 2 to 4 |
| Hostel QR sheet | 5 to 40 over the window | 4 to 6 hostels |
| Discord self promo channel | 5 to 30 | 3 to 5 |

After 7 days the real rates replace these, from /admin/funnel ("Where people came from") and the
`alerts_by_channel` block of /cron/funnel. A channel below 1% visit to alert after 150 visits is dropped.

## 3. Attribution (live since 1 Oct, deploy f749a0d)

Every link we post is made with:

    python scripts/outreach_link.py <source> <medium> <campaign> <where>

* source: facebook, reddit, instagram, discord, creator, newsletter, qr, email
* medium: group, post, comment, story, bio, dm, email
* campaign: day-of-the-dead, yi-peng, web-summit (these also prefill the dates on the site)
* where: the exact group, thread, creator or hostel, for example `chiang-mai-digital-nomads`

What the site records:
* first touch is held for 90 days in the browser;
* every match alert stores the channel on the alert row itself, and the confirm link (usually opened
  in a mail app's browser) inherits it, so the signup that follows is credited to the same post;
* /admin/funnel shows source, campaign and where posted, with visits, alerts, cards, signups, trips;
* /cron/funnel `phase.alerts_by_channel` lists every alert by post: set, confirmed, converted.

A shared "I'm going" card or a shared event page carries `utm_medium=share`, so the second traveler a
card brings in is counted separately from the post that brought the first.

## 4. Rules for every post

* Posted only as RuinMyTrip (the Facebook Page "Ruin My Trip Travelers", Instagram @ruinmytripcom).
  Never as a personal profile pretending to be a traveler, never "I'm going" unless that person is.
* One line of disclosure in every post: "I help run RuinMyTrip."
* Read the group or subreddit rules the same day; if promotion is not allowed, ask the admin first
  or skip. Never more than one post per group per event.
* The post must be useful with the link removed: the practical warnings come first.
* No fake engagement, no second accounts, no paid followers, no DMs to strangers.

## 5. Copy

### Oaxaca, Day of the Dead (Facebook groups, our Page)

> Heading to Oaxaca for Day of the Dead this year? A few things people wish they had known:
> the cemetery vigils in Santa Cruz Xoxocotlán run on the nights of 31 October and 1 November,
> from early evening into the next morning, the crowds there were counted at more than 30,000 as
> far back as 2005, and it is one of the busiest weeks of the year for rooms, so book early.
>
> We put the dates, the warnings and the sources on one page, and it has a free alert: add the
> nights you will be there and you get one email when another traveler's dates overlap yours.
> No account needed. [link]
>
> I help run RuinMyTrip. If something on the page is wrong, tell me and I will fix it.

Facts in this copy must match the page on the day it is posted. Re-read the page before posting.

### Chiang Mai, Yi Peng (r/ThailandTourism own content allowed with disclosure; Facebook nomad groups via admin)

> Yi Peng 2026: the full moon is Tuesday 24 November. No official 2026 program has been published
> yet, so check what a lantern ticket actually includes before you pay. What is confirmed, what is
> not yet, and the warnings travelers wish they had known are on one page.
>
> If you are going solo, there is a free match alert: put in your dates and you get told when
> another traveler will be in Chiang Mai the same nights. Disclosure: I help run the site. [link]

### Lisbon, Web Summit (Meetup organizers, Lisbon nomad groups, r/WebSummit, event newsletters)

> Going to Web Summit alone? 9 to 12 November at the MEO Arena. The red metro line runs from the
> airport to Oriente next to the venue, and almost nobody plans the day off (tram 15 to Belém,
> or the train to Sintra from Rossio).
>
> We made a page for the hours around the talks: who else is in town on your dates. Add your dates
> for a free alert when someone overlaps. I help run RuinMyTrip. [link]

### Instagram (our account)

* Carousel per event: slide 1 the event card image (/card/event/{slug}.png), slides 2 to 4 one
  warning each, last slide "Add your dates, find who else is going. Link in bio."
* Bio link rotates to the event whose window is closest, tagged `instagram bio <campaign> bio`.
* Stories: location sticker for the city, "Going?" question sticker, link sticker tagged per story.
* Reply only to people who comment or DM us first.

### Micro creators and newsletters (email, one per person, no follow up chain beyond one reminder)

> Subject: A free resource for your readers going to [event]
>
> Hi [name], I help run RuinMyTrip. We wrote a page for travelers going to [event]: dates checked
> against sources, the warnings people wish they had known, and a free alert that tells someone
> when another traveler will be there the same nights. If it is useful to your readers, feel free
> to link it; here is a link with your name on it so we can show you exactly what it did: [link].
> No payment asked or offered. If anything on it is wrong, I would like to know.

Candidates are listed in docs/CHANNELS_RESEARCH_20261001.md section 8.

### Hostels (email to the hostel, then a printed A5 sheet only with their yes)

A5 sheet: the event card, the three top warnings, a QR code to the page tagged
`qr <campaign> <hostel-slug>`. Candidates: Casa Angel and Hostal Central (Oaxaca), Stamps
Backpackers (Chiang Mai), Home Lisbon and Sunset Destination (Lisbon).

### Discord

Only servers with a #self-promo or #events channel. Same copy as Facebook, shortened to three lines.

## 6. Order of work

| When | Oaxaca | Chiang Mai | Lisbon |
|---|---|---|---|
| Week 1 (1 to 7 Oct) | 5 Facebook groups via admin request, IG carousel | r/ThailandTourism post, 3 nomad groups via admin | Meetup organizer message, 2 Lisbon groups, r/WebSummit post, Side Events Guide listing only if a real event exists |
| Week 2 | 2 creators, 2 hostels, IG stories | 2 creators, Stamps Backpackers, Discord | Lisbon Letter, Lisbon Community Events, 2 creators |
| Week 3 | Last push before 31 Oct, card shares from early travelers | r/chiangmai discussion post, IG | Newsletter round two, IG |
| Week 4+ | Window closes, ask travelers for reviews | Hostels, IG | Window closes 12 Nov |

## 7. What needs a person

* Facebook: group posts go out as the Page; joining groups and admin requests need the Page admin
  profile signed in. A go ahead from Nima per batch.
* Reddit: needs an account with history that discloses its affiliation. The previous attempt was
  blocked; Nima decides whether to use a real, disclosed account.
* Any paid placement or sponsorship needs Nima's approval first.
