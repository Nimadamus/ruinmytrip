# First controlled cohort

Everything needed to run the first ten real travelers, in one file. Nothing here has been sent.

---

## 1. The wedge, locked

**CITY:** Miami (`/d/miami-usa`)

**WINDOW:** Tuesday 1 December to Monday 7 December 2026

**WHY:** Miami Art Week. Art Basel Miami Beach runs 4 to 6 December at the Miami
Beach Convention Center, with VIP previews from the 3rd. The satellite fairs
(Untitled, NADA, Scope) and the Wynwood programme fill the surrounding days, so
the travel window is the whole week rather than the fair days. Roughly 80,000
visitors, heavily first time, heavily group travel, and already publicly asking
what to plan. Booking happens now: the window is about eleven weeks out from
mid September, which is exactly when this week gets planned.

Not chosen and why: a Dolphins or Heat weekend is closer to our reachable
audience but is booked two weeks out, which leaves no time to build anything.
Miami Open and Ultra are late March 2027, too far for a first cohort.

**PRIMARY NETWORK GOAL:** `cities_with_overlap` goes 0 to 1.

**SECONDARY:** `trips_with_overlap` greater than 0.

Both are on `/admin/funnel` under "Is the network working", and both are
recomputed from trip rows rather than tracked, so neither can be gamed.

---

## 2. Who qualifies

The cohort is not ten people willing to register. A qualifying member:

- is genuinely going to Miami, or genuinely considering it, within 1 to 7 December
- knows roughly which days
- is willing to enter a real trip with real dates
- would plausibly use a saved place or a plan item, which usually means they are
  not travelling on a fully prearranged itinerary

A disqualifying signal: somebody who will register to be helpful but has no
Miami plans. They add a row to the signup count and nothing to the network, and
they make the funnel read better than the product deserves.

**Nobody's plans get created for them.** If a member will not enter dates, that
is data about the product, not a problem to work around.

---

## 3. Acquisition, source by source

Ten people. Quality and date overlap beat volume.

| Source | Where exactly | Who qualifies | Angle | Target |
|---|---|---|---|---|
| Direct contacts | People you or I already know | Anybody with Miami plans in the window, or who would go | Straight ask, Variant B | **4 to 6** |
| Art Week communities | Fair mailing lists, gallery Discords, Miami Art Week threads | Attendees planning the week | Variant A | **2 to 3** |
| Reddit | `r/Miami`, `r/ArtBasel`, `r/solotravel`, `r/femaletravels` | People asking what to plan for Art Week | Variant A as a genuine answer first | **2 to 3** |
| X | Replies to people posting Miami or Art Week plans | Same | Variant C | **1 to 2** |
| Instagram | Comments on Art Week posts and Wynwood location tags | Same | Variant C | **1 to 2** |
| Quora | Existing "what to do in Miami in December" questions | Low intent, slow | Variant A shortened | **0 to 1** |

Reddit is the highest value and the only channel where one bad post ends the
channel permanently. It needs an account with history, which means Nima's or
none.

---

## 4. What each member is asked to do

1. Verify the account
2. Create a real Miami trip
3. Enter real dates inside 1 to 7 December
4. Save at least one place
5. Add at least one plan item with a time
6. Open `/d/miami-usa/travelers` and see whether anybody overlaps
7. If somebody genuinely relevant is there: follow, message, or ask to join

Steps 3 and 6 are the ones that matter. A trip with no dates cannot overlap
anything and is invisible to the entire social half of the site.

**Step 7 is optional and stays optional.** Pushing people into a social action
to move a number produces a number that means nothing.

---

## 5. The pass/fail question

> Can ten real travelers create one genuinely useful network cluster?

Success starts when at least some real members discover another real traveler
whose dates genuinely overlap theirs.

If ten appropriate people produce zero overlap, the recruitment was wrong or the
window was wrong, and recruiting a hundred more would only make a bigger version
of the same nothing. Investigate before scaling.

---

## 6. Cohort 2 is decided by data, not scheduled

After the first ten, choose between:

- **A. Deepen the same Miami window.** Indicated when overlap formed but was thin.
- **B. Recruit a second Miami date cluster.** Indicated when overlap formed and
  members acted on it. The natural next anchor is Miami Open and Ultra, late
  March 2027.
- **C. Open another destination.** Indicated only when Miami produces repeat
  overlap without our involvement. Lisbon anchored on Web Summit is the next wedge.

Decided on overlap density, activation, social behaviour, return behaviour and
what people actually said. Not on signup count.

---

## 7. What we expect by 100 members

**Hypotheses, not facts.** There is no baseline. The point of writing them down
is to find out which are wrong.

| Measure | Hypothesis at 100 signups |
|---|---|
| Verified | 70 to 85 |
| Created a trip | 45 to 60 |
| Entered real dates | 35 to 50 |
| Saved a place or added a plan | 30 to 45 |
| Trips with real overlap | 20 to 35 |
| Network activated | 20 to 30 |
| Follow, message or join request | 10 to 20 |
| Social activation rate | 40 to 60 percent |
| Returned another day | 25 to 40 |

Social activation rate is the one that matters most. If people see an
overlapping traveler and do nothing, the problem is the product and more
members will not fix it.

---

## 8. Message drafts

Not sent. Lead with the trip, never with the product.

### Variant A, community post

> Going to Miami for Art Week, 1 to 7 December? I built a page where you add
> your dates and see who else is in the city the same days, what they are
> planning, and which places people are saving. Free, no app.
>
> Mostly I want to know whether the satellite fairs are worth the extra days or
> whether three days is enough. Dropping mine in case anybody else is deciding
> the same thing.

### Variant A short, comment or reply

> If it helps, I put my dates on ruinmytrip.com and it shows other people in
> Miami the same week plus what they have planned. Useful for working out
> whether to stretch it past the fair days.

### Variant B, direct message

> Saw you are heading to Miami in early December. I run a small site where you
> put in your dates and it shows other travelers in the city that week and what
> they have planned. It is early and quiet, so no promises, but you will at
> least see whether anybody is around when you are. Happy to hear what is wrong
> with it.

### Variant C, X

> Miami Art Week is 1 to 7 December. The hard part is not the fairs, it is
> working out which days are worth it and who else is around.
>
> Add your dates and you can see other travelers in the city that week, what
> they are planning, and which places everyone is saving.

### Variant C, Instagram or TikTok caption

> Miami Art Week, 1 to 7 December. Add your dates and see who else will be
> there, what they are planning, and the places everyone is saving. Link in bio.

### Quora, as an answer not a pitch

Answer the question properly first: the island versus mainland split, Art Week
dates, which neighbourhoods. One closing line only:

> If you want to see who else is there the same week, I keep my dates on
> ruinmytrip.com.

---

## 9. The dashboard to watch

All of it is at `/admin/funnel`, or as JSON at `/cron/funnel?key=...&days=30`.

**Acquisition:** arrivals (crawlers excluded), signups, verified.
The arrival to signup rate is deliberately blank until the arrival counter has
been running for the whole window. It will fill in by early October.

**Activation:** first trips, planning actions (saved place or plan item).

**Network:** trips with overlap, network activated, cities with overlap.

**Social:** social activated. Follows, messages and join requests appear
individually under First actions.

**Retention:** came back another day, meaning a later calendar day than the one
they joined, not a later timestamp.

No pair identities anywhere. Activation is a count of members, never of who
overlapped with whom.

---

## 10. Runbook

### Before

- [ ] DMARC TXT record published and verified (`_dmarc.ruinmytrip.com`)
- [x] Miami place data enriched to the provider ceiling: 39 substantive pages of 110 became
      47 of 115, via a deep enrich pass that filled 28 addresses, 28 websites, 27 phones,
      35 coordinate sets and 10 opening hours sets while creating nothing
- [x] Six Miami editorial place reviews published, each 800 words or more with a map and a
      source list: Vizcaya, PAMM, Wynwood Walls, Joe's Stone Crab, Versailles, Bill Baggs
- [x] Miami destination editorial published
- [x] Funnel green and reporting honestly, crawler traffic excluded
- [x] Production email proven end to end into a real inbox
- [ ] Error monitoring checked on the day
- [ ] Final public walkthrough repeated on the day

### Launch

- [ ] Invite the first users, source by source, in the order in section 3
- [ ] Watch the funnel daily: signups, verified, trips, dates entered
- [ ] Watch for signup and email errors specifically, not just totals
- [ ] Watch `cities_with_overlap` for the 0 to 1 transition

### After

- [ ] Review all ten individually: what they did, where they stopped
- [ ] Collect actual friction in their words, not inferred from the funnel
- [ ] Read the funnel against the hypotheses in section 7
- [ ] Decide Cohort 2 as A, B or C per section 6

---

## 11. Standing constraints

No fake travelers, trips, overlaps, reviews, activity or follower counts. If the
network is empty the funnel says so. `nimatest2001` stays untouched and gets no
activity built around it. No outreach without Nima. No external accounts, no
paid services, no purchases.
