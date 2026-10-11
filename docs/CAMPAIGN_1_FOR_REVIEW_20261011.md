# Campaign 1: first real visitors (FOR REVIEW, NOTHING SENT OR POSTED)

Prepared 2026-10-11 (Pacific). Starts only after the owner approves, and only once the review flow
and analytics are confirmed healthy on production (see the deploy log). Builds on
`FIRST_100_CONTENT_PACK_20261011.md` and the rules table in `FIRST_100_CAMPAIGN_DRAFTS_20261011.md`.

Goal for the first two weeks: 10 real reviews from people who really went, 30 travel maps made,
measured with the site's own verified human count (an estimate) by link tag.

## Week 1: people the owner knows (the only outreach in week 1)

Personal messages, one at a time, from the owner's own phone or email, to 10 to 20 friends who
travel. Never a group blast, never a second message to someone who does not reply after one
reminder. Draft (the owner should change it into how he actually talks):

> Hey [name], I have been building a travel site called RuinMyTrip and I would love your help
> with one small thing. Think of one place you went on a trip (a restaurant, a hotel, a museum,
> anything) and give it a quick honest review: a few stars and two or three lines on what was good
> and what nearly ruined it. No account needed to start. And if you want, color in every country
> you have been to here, it takes 30 seconds: [map link]. Honest feedback on the site itself is
> welcome too, especially anything that is confusing or broken.

Links (one tag per person group so replies can be told apart):
- Map: `https://ruinmytrip.com/map?utm_source=friends&utm_medium=post&utm_campaign=launch-map&utm_content=invite`
- A place to review: the place page itself, with `?utm_source=friends&utm_medium=post&utm_campaign=launch-review&utm_content=invite`

What not to do: ask for 5 stars, ask anyone to review a place they have not been, write a review
for someone, or reuse a friend's words anywhere without asking.

## Week 2: the first public pieces (owner's own accounts, each approved first)

1. **Video 1, "My number"** (TikTok and Reels): the owner taps his real countries on the live map,
   says his real count and the one country he would go back to. Script and captions in the content
   pack. The owner films his own phone screen; nothing is staged.
2. **Quora answer type 1** ("How do I make a map of the countries I have visited?"): answer
   written in his words, disclosure line at the end, one tagged link.
3. **Pinterest**: one pin for the map (the downloaded image of his own map) and one each for two
   city pages he knows (his own photo, "Opening hours, directions and honest reviews").
4. **Video 4, "Before you book [city]"** for one city the owner knows well and that already shows
   in search (Paris, Amsterdam, Stockholm, Zurich, Lisbon). Every fact rechecked live the day it is
   posted and dated on screen.

Show HN, r/SideProject and Product Hunt wait until there are at least 10 real reviews, so that
strangers clicking into the site do not land on empty pages.

## What gets measured, daily during the campaign

For each tag: verified humans, maps made (map page with countries chosen), map saves, review form
opens, reviews published, signups, and return visits within 7 days. Read from `/admin/proof` and
the KPI snapshot. A piece that brings visitors but no maps or reviews is changed or stopped, not
repeated.

## Needs from the owner

- Approval of the friend message (in his own words) and the list of people.
- His real numbers and stories for the videos, or a decision to skip a video.
- Which city he knows well enough for Video 4.
