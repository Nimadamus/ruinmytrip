# Campaign 1: the first three channels (FOR REVIEW, NOTHING SENT OR POSTED)

Prepared 2026-10-11 (Pacific). Starts only on the owner's go. The owner posts and sends everything
from his own accounts, in his own words. No fake accounts, reviews, engagement or numbers.

Before day 1: deploy `feat/campaign-measure-20261011` (6e85094) so the per-channel report counts
reviews and map shares by link tag (today it only counts signups and trips).

| | 1. People the owner knows | 2. Short video: "What's your number?" | 3. Quora answers |
|---|---|---|---|
| Concept | Ask 10 to 20 friends who travel to rate one place they have really been, and color in their map | The owner taps his real visited countries on the live map; says his count and the one he would go back to | Useful answers to questions about places Google already shows us for, and about visited country maps, with a disclosure line |
| Audience | Friends and family who travel (warm, will actually write) | Travelers 20 to 40 who share country counts and maps | People planning a visit to Stockholm, Zurich, Paris, Amsterdam, Dublin, Budapest |
| Content | One personal message each (draft below), one reminder at most | Video 1 in the content pack (20 s, 9:16, his own screen recording and voice) on TikTok and Reels | 2 or 3 answers a week, rewritten by the owner; never the same text twice |
| Destination URL | `https://ruinmytrip.com/map?utm_source=friends&utm_medium=dm&utm_campaign=launch-review&utm_content=invite` | `https://ruinmytrip.com/map?utm_source=tiktok&utm_medium=bio&utm_campaign=launch-map&utm_content=video1` (instagram for Reels) | The exact place page, e.g. `https://ruinmytrip.com/p/vasa-museum-stockholm?utm_source=quora&utm_medium=post&utm_campaign=launch-review&utm_content=vasa` |
| Conversion action | A review published (then a map) | A map shared, downloaded or saved | Review form opened or review published by someone who has been; second page viewed |
| Measurement | Per-channel report: reviews, review_started, map_actions for `friends`; verified humans in `/admin/proof` | map_views and map_actions for `tiktok` / `instagram` against the platform's own view counts | reviews, review_started and human sessions for `quora`, per `utm_content` |
| Goal, 14 days | 10 real reviews | 30 maps shared or saved | 50 verified visitors, 2 reviews |
| Stop rule | Never message anyone twice after one reminder | 3 videos under 10 verified visitors in total: stop | 6 answers under 10 verified visitors in total: stop |

Why these three: all three are allowed by the venue's own rules, need no money, and lead with the
two things that work for a single stranger today (the map needs no account; a place page answers a
real question). Show HN and r/SideProject come after there are at least 10 real reviews, so people
clicking in do not land on empty pages. r/travel, r/solotravel and r/digitalnomad ban this outright.

The places for Quora and for asking friends: Vasa Museum, Zeughauskeller, Ekstedt, Ratana, Au Pied de
Cochon, Book of Kells, Hungarian National Museum, Anne Frank House (search impressions and a full
write up each). Any fact quoted is rechecked live the day it is posted.

## Friend message (draft; the owner rewrites it in his own voice)

> Hey [name], I have been building a travel site called RuinMyTrip and could use your help with one
> small thing. Think of one place from a trip (a restaurant, a hotel, a museum, anything) and give
> it an honest review: a few stars and two or three lines on what was good and what nearly ruined
> it. No account needed to start. And if you want, color in every country you have been to, it
> takes 30 seconds: [link]. Tell me anything that is confusing or broken.

Never ask for 5 stars, never ask anyone to review somewhere they have not been, never write a review
for someone.

## Needs from the owner

1. Go for the measurement deploy and for day 1.
2. The friend list (he sends; nothing goes out from the site).
3. His real country count and a 20 second screen recording for the video.
