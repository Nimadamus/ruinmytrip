# Campaign 1 assets (READY FOR REVIEW, NOTHING POSTED OR SENT)

Prepared 2026-10-11 (Pacific). The owner posts and sends everything from his own accounts. Plan and
stop rules: `CAMPAIGN_1_FOR_REVIEW_20261011.md`. Measurement is live (see the end).

## Files (on the owner's PC, `C:\Users\BL\rmt_campaign1\`)

| File | Use |
|---|---|
| `video_demo/whats_your_number_1080x1920.mp4` | TikTok and Reels, 19.9 s, silent (add a sound in the app) |
| `video_demo/cover_1080x1920.png` | Cover frame for both |
| `video_demo/instagram_feed_1080x1350.png` | Instagram feed post |
| `video_demo/map_card.png` | The map image the site itself makes (1200x630) |
| `build/build_video.py`, `build/build_still.py` | Rebuild everything in about a minute |

The demo shows 15 sample countries and never says whose they are. Before posting, rebuild with the
owner's real countries so the "I've been to N countries" on screen is true:
`python build/build_video.py video_mine 840 250 ...` (ISO numeric ids), then `python build/build_still.py video_mine`.

## Tracking links

Every link lands on a live page. The report counts them by source, campaign and the label after `utm_content`.

| Where | Link |
|---|---|
| Friends, personal message | `https://ruinmytrip.com/map?utm_source=friends&utm_medium=dm&utm_campaign=launch-review&utm_content=invite` |
| Friends, one place to rate (swap the place) | `https://ruinmytrip.com/p/vasa-museum-stockholm?utm_source=friends&utm_medium=dm&utm_campaign=launch-review&utm_content=rate-vasa` |
| TikTok bio | `https://ruinmytrip.com/map?utm_source=tiktok&utm_medium=bio&utm_campaign=launch-map&utm_content=video1` |
| Instagram bio | `https://ruinmytrip.com/map?utm_source=instagram&utm_medium=bio&utm_campaign=launch-map&utm_content=video1` |
| Instagram story link sticker | `https://ruinmytrip.com/map?utm_source=instagram&utm_medium=story&utm_campaign=launch-map&utm_content=story1` |
| Quora, Vasa answer | `https://ruinmytrip.com/p/vasa-museum-stockholm?utm_source=quora&utm_medium=post&utm_campaign=launch-review&utm_content=vasa` |
| Quora, Anne Frank House answer | `https://ruinmytrip.com/p/anne-frank-house-amsterdam?utm_source=quora&utm_medium=post&utm_campaign=launch-review&utm_content=annefrank` |
| Quora, Book of Kells answer | `https://ruinmytrip.com/p/book-of-kells-experience-at-trinity-college-dublin?utm_source=quora&utm_medium=post&utm_campaign=launch-review&utm_content=kells` |

People who type `ruinmytrip.com/map` from the video arrive untagged (direct). Watch direct map views
on the days a video is posted alongside the tagged numbers.

## TikTok and Reels captions

1. What's your number? Tap every country you've been to and watch it count. Free, no sign up. Link in bio. #travel #travelmap #countriesvisited
2. I built a travel map you can fill in in 30 seconds. How many have you done? Link in bio.
3. Be honest: how many countries? Mine's on screen. Yours goes in the comments.

On screen text is already in the video. Pin a comment with the number you show.

## Instagram feed caption

What's your number? Tap every country you've been to on ruinmytrip.com/map and it makes this image for
you. Free, no account. Link in bio. Drop your number below.

## Personal invitations (the owner rewrites each in his own voice; one reminder at most)

**Text or WhatsApp, short**
> Hey [name], I built a travel site and I'd love your honest take. Tap every country you've been to
> here, takes 30 seconds, no account: [friends invite link]. Tell me if anything is confusing.

**Text, the review ask**
> Hey [name], quick favor. You went to [place] in [city], right? Could you give it a real review on
> my site? A few stars and two lines on what was good and what nearly ruined it: [rate link for that place].
> Totally fine to be harsh, the bad parts are the useful parts.

**Email**
> Subject: Small favor for my travel site
>
> Hi [name], I've been building RuinMyTrip, a travel site for honest reviews: what a place really
> costs and what you wish you'd known. It has almost no reviews yet, and I'd much rather the first
> ones come from people I trust. If you have five minutes, pick one place you've really been and
> review it here: [rate link]. And if you want, color in every country you've been to: [friends invite link].
> Reply and tell me anything that's broken or confusing. Thank you!

Rules: only people who have really been there; never ask for 5 stars; never write a review for anyone.

## Quora answers (drafts; the owner rewrites, rechecks prices on the official site the day he posts)

Each ends with the disclosure. Post only on questions actually asking this.

**"Is the Vasa Museum in Stockholm worth it?"**
> Yes, and it is hard to name a better museum in Scandinavia. It is built around the warship Vasa,
> which sank on its maiden voyage in 1628 and was raised in 1961; about 98 per cent of what you see is
> original. Go at opening or in the last two hours, because midday in summer is packed, and start
> at the top gallery and work down. Adult entry is 240 SEK and under 18s are free; the combination
> ticket with Vrak is 359 SEK and valid 72 hours, so only buy it if you have a second day. Allow 90
> minutes to two hours. Tram 7 or the Djurgården ferry from Slussen gets you there.
> Disclosure: I run RuinMyTrip, where I keep a page on it with hours and a map: [Quora Vasa link]

**"Do you need to book the Anne Frank House in advance?"**
> Yes, always. Entry is by timed online ticket only, slots sell out, and there is no walk up option,
> so if you have not booked and you leave tomorrow, plan something else. Adults pay 16.50 euros,
> ages 10 to 17 pay 7 euros, under 10s 1 euro (each includes a 1 euro booking fee); the I amsterdam
> Card gives no discount. Take the 9:00 slot if you can, pack light (the stairs in the annexe are
> steep and narrow) and allow about 90 minutes. Tram 13, 17 or 19 to Westermarkt.
> Disclosure: I run RuinMyTrip, which has a page on it: [Quora Anne Frank link]

**"Is the Book of Kells worth the money?"**
> Honestly, it depends what you go for. The ticket starts at 26 euros and the manuscript itself is two
> pages behind glass in a dim room, on a one way route at group pace. The Long Room library upstairs
> is the part most people remember, so give it more of your time than the manuscript. Book a timed
> slot ahead and take an early one; plan about 90 minutes.
> Disclosure: I run RuinMyTrip, where travelers can say whether it was worth it: [Quora Kells link]

## Measurement (live since 2026-10-11 17:21 PDT)

`/admin/funnel`, table "Where people came from", one row per source, campaign and label: human
visits (the same verdict as `/admin/proof`), signups, confirmed, trips, reviews (and review forms
opened by people), maps shared or saved (and map views), came back on a later day, suspected bots
and unverified visits. The same rows are in `/cron/funnel` under `acquisition`. Our own checks use
`utm_content=selfcheck` and are shown apart.
