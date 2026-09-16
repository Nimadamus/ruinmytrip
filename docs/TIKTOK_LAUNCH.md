# TikTok, ready to open

**The account does not exist and I have not created it.** Everything below is what is needed the
moment you say yes, including the landing paths, which are verified live.

## The account

| Field | Value |
|---|---|
| **Preferred username** | `ruinmytrip` |
| **Backup 1** | `ruinmytripcom` |
| **Backup 2** | `ruinmytrip.travel` |
| **Backup 3** | `getruinmytrip` |
| **Do not use** | anything with underscores, numbers or a year. An unmemorable handle costs more than three extra characters |
| **Display name** | RuinMyTrip |
| **Profile image** | The site mark on a plain background, legible at 40px. **Not** a stock beach photo: at thumbnail size every travel account looks identical and a wordmark does not |
| **Link in bio** | `https://ruinmytrip.com/?utm_source=tiktok&utm_medium=bio&utm_campaign=profile` |

**Bio, exactly:**

> Find the people going where you're going.
> Post your dates for a city, see whose overlap.
> New, quiet, and honest about it.

That third line is doing real work. An account with no followers claiming to be a community reads as
a lie to anybody who clicks through, and saying it first is the only version that survives contact
with the actual site.

## Hashtags, and why there are almost none

Three per video, maximum, and only where the tag is a real place or event people search:
`#oktoberfest`, `#solotravel`, `#chiangmai`. **No `#fyp`, no `#viral`, no `#travel`.** Those are
noise on a post with no engagement behind it and they are the first signal a reader uses to decide
an account is a marketing account.

## The first ten videos, in posting order

Full scripts, on screen text and captions are in `docs/ACQUISITION_VIDEO.md`. This is the order, the
tracked link and the hashtags.

**The first three have to explain the idea to somebody who has never heard of it, with no following
to lend them credibility.** That is why 1, 2 and 3 are all a traveler problem stated plainly, and
why the word "app" does not appear in any of them.

| # | Video | Hook, first 2 seconds | Campaign | Tracked link | Hashtags |
|---|---|---|---|---|---|
| 1 | Going to Oktoberfest alone? | "Going to Oktoberfest alone?" | `oktoberfest` | `https://ruinmytrip.com/d/munich-germany?utm_source=tiktok&utm_medium=bio&utm_campaign=oktoberfest` | #oktoberfest #munich #solotravel |
| 2 | Would you share a table with strangers? | "You are going to share a table with strangers anyway." | `oktoberfest` | same as 1 | #oktoberfest #solotravel |
| 3 | Solo trip, not alone the whole time | "Solo travel does not mean alone the whole time." | `solo-overlap` | `https://ruinmytrip.com/?utm_source=tiktok&utm_medium=bio&utm_campaign=solo-overlap` | #solotravel |
| 4 | Would you meet a traveler whose dates overlap? | "Would you meet a stranger whose trip overlaps yours?" | `web-summit` | `https://ruinmytrip.com/d/lisbon-portugal?utm_source=tiktok&utm_medium=bio&utm_campaign=web-summit` | #solotravel #lisbon |
| 5 | The honest empty screen | "This is what my site says when it has nobody on it." | `honest-empty` | `https://ruinmytrip.com/?utm_source=tiktok&utm_medium=bio&utm_campaign=honest-empty` | none |
| 6 | Dia de Muertos, which night | "Do not go to Oaxaca for Day of the Dead without reading this." | `day-of-the-dead` | `https://ruinmytrip.com/d/oaxaca-mexico?utm_source=tiktok&utm_medium=bio&utm_campaign=day-of-the-dead` | #oaxaca #diademuertos |
| 7 | The conference app problem | "Your conference app matches you with 70,000 people." | `web-summit` | same as 4 | #websummit #lisbon |
| 8 | Traveling solo to Bangkok? | "Solo in Bangkok over New Year?" | `new-year-2027` | `https://ruinmytrip.com/d/bangkok-thailand?utm_source=tiktok&utm_medium=bio&utm_campaign=new-year-2027` | #bangkok #solotravel |
| 9 | Which city is easiest to meet travelers in? | "Which city is easiest to meet other travelers in?" | `dates-not-places` | `https://ruinmytrip.com/d/chiang-mai-thailand?utm_source=tiktok&utm_medium=bio&utm_campaign=yi-peng` | #chiangmai #backpacking |
| 10 | Why the dates matter more than the city | "Everyone asks where. Almost nobody asks when." | `dates-not-places` | `https://ruinmytrip.com/?utm_source=tiktok&utm_medium=bio&utm_campaign=dates-not-places` | none |

**The bio link changes with the video.** TikTok allows one link, so it points at the city the current
video is about, and back to `campaign=profile` between cohorts.

## Cadence

**One a week, Wednesday.** Ten scripts is ten weeks of runway. Reply to comments. Do not follow
people to get follows, do not comment on other accounts for attention, never send a message.

## The landing path, verified

A person who watches the Bangkok video and taps the bio link lands here:

1. **`/d/bangkok-thailand?utm_source=tiktok&utm_medium=bio&utm_campaign=new-year-2027`** opens with
   the community block: the city, the New Year window, a **Post your Bangkok dates** button with
   27 December to 2 January already in it, **See who is going**, **Ask the community**, and one line
   explaining the site to somebody who has never seen it. All four controls are within two screens
   at 390px.
2. **Post your dates** goes to signup with the whole link preserved, and the signup page says
   "Join and post your Bangkok dates, 27 December to 2 January. They are already filled in."
3. Signup returns to that filled form. A trip typed before the address is confirmed is **held and
   written the moment it is confirmed**, not discarded.
4. The trip lands on `/matches` with that trip highlighted, and if nobody overlaps, the page says so
   plainly and offers follow, ask, everyone in the city, and invite.

All seven campaign paths were verified end to end on production with zero failures. For the three
without a campaign (`solo-overlap`, `honest-empty`, `dates-not-places`) the link is the homepage,
which carries the one sentence explainer and the city search.

## What decides whether we keep going

**Human visits carrying `utm_source=tiktok`**, read in `/admin/funnel` under "Acquisition, now".

* **Keep going:** any video reaches 100 views **and** 2 real human visits.
* **Change the hook, not the channel:** views arrive, visits do not.
* **Stop at three:** three videos, no visits at all. The effort moves to Facebook groups.

**Not followers.** A thousand followers who never click is worth less than four travelers who post
dates, and the milestone counts the second thing.
