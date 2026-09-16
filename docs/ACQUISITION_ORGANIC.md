# The organic acquisition report

Read from Search Console on 2026-09-16, 90 days to that date. **No page was created, no URL changed,
no title touched, and the nine page title experiment is untouched.** This is what the data says and
what it is worth doing about it.

## The headline, said plainly

**0 clicks. 1,469 impressions. Average position 45.**

Nothing on this site ranks. Position 45 is page four or five of a search result, which nobody reads.
Organic search will not supply the first hundred travelers, and any plan that assumes it will is
wishful. What organic *can* do, right now, is make sure the handful of people who do arrive find the
product rather than an article.

That reframes the whole exercise. The question is not "how do we rank" but **"of the people who
already land here from search, how many ever learn what this site is for"**. Until today the answer
was close to nobody, and that is fixed below.

## What is already fixed, as of 2026-09-16

**Every page type that earns impressions now offers the social product.** Place pages, guides and
discussion posts carried no link at all to a city's community, its travelers or the trip form. Our
single most seen page in ninety days is a place page with 188 impressions, and somebody arriving on
it from Google could read it and leave without ever learning there is a Dublin community with dates
in it.

One shared component, `views/_dest_social_cta.php`, is now included on all three page types wherever
the city is genuinely known. It carries the campaign window when one is running, so a reader of the
Munich guide in September is offered the Oktoberfest dates rather than an empty form. It claims no
numbers, because on most cities the honest number is zero.

## Where the impressions actually are

| Page | Impressions, 90d | Avg position | What it is |
|---|---|---|---|
| `/p/book-of-kells-experience-at-trinity-college-dublin` | 188 | 68.0 | Place page, Dublin |
| `/blog/san-francisco-hotel-tax-2026` | 58 | 11.6 | Article, and **the best position on the site** |
| `/g/chiang-mai-thailand-travel-guide` | 38 | 89.9 | Guide, and a cohort city |
| `/p/benaki-museum-of-greek-culture-athens` | 36 | 40.7 | Place page, Athens |
| `/d/amsterdam-netherlands` | 28 | 44.2 | **Destination page**, and the best of them |
| `/d/lisbon-portugal` | 26 | 49.5 | Destination page, Web Summit cohort, in the title test |
| `/g/zanzibar-tanzania-travel-guide` | 23 | 89.7 | Guide |
| `/p/anne-frank-house-amsterdam` | 21 | 76.5 | Place page |
| `/g/cancun-mexico-travel-guide` | 19 | 76.6 | Guide |
| `/login` | 17 | 19.1 | A sign in form ranking for "ruins trip" |

**The pattern matters more than any single row.** Place pages and guides carry roughly four times the
impressions of destination pages, and destination pages are where the entire social product lives.
That is the gap the shared component closes, and it is the highest value organic change available
without writing a single new page.

## Queries within reach, position 5 to 40

These are real queries with real impressions where the page already ranks well enough that a better
match could produce a first click.

| Query | Position | Page | Honest assessment |
|---|---|---|---|
| san francisco hotel tax rate | 7.8 | `/blog/san-francisco-hotel-tax-2026` | Genuinely satisfies it. **Best ranking asset on the site**, and nothing to do with travel matching |
| official museum of cycladic art athens opening hours 2026 | 5.0 | `/p/museum-of-cycladic-art-athens` | Satisfied if the hours are current. Worth a data check, not a rewrite |
| what is their cancellation policy | 6.0 | `/p/antiche-carampane-venice` | We probably do not answer this. Leave it |
| cafe nini | 7.0 | `/p/nini-s-coffeebar-amsterdam` | Brand query, satisfied |
| ekstedt stockholm price | 9.0 | `/p/ekstedt-stockholm` | Satisfied if the price band is current |
| cafe lisboa lisbon | 9.0 | `/p/cafe-lisboa-lisbon` | Satisfied |
| chapter one dublin tasting menu price 2026 | 10.0 | `/p/chapter-one-dublin` | Satisfied |
| city tax nice | 11.0 | `/d/nice-france` | A destination page ranking for a tax question |
| munich museums sunday 1 euro | 24.0 | `/review/128/...` | **A Munich query, in the Oktoberfest window** |
| amsterdam city tax | 35.0 | `/d/amsterdam-netherlands` | Our best destination page, ranking for a tax question |

**What this table really says.** Every query we are close on is a *fact* query: a price, an opening
time, a tax rate. None of them is a travel matching query. The people finding this site through
search are not looking for travel companions, they are looking for a number. That is why the shared
component matters and why chasing these queries harder would be chasing the wrong audience.

## The intent we actually want, and where it currently lands

Mapped against real URLs. **No page was created for any of these.**

| Intent | Best existing URL | Impressions today | Does the page satisfy it | What makes us different |
|---|---|---|---|---|
| meet travelers in {city} | `/d/{slug}/travelers` | not in top pages | Yes, and honestly says when empty | Dates, not profiles |
| travel buddy {city} | `/d/{slug}/travelers` | not in top pages | Partly. The page answers "who is going" rather than "find me a buddy" | No swiping, no matching algorithm, just overlapping dates |
| solo travel {city} | `/d/{slug}` | 9 to 28 on the best cities | The community block does; the editorial does not | A city page that opens with its people |
| people going to {event} | `/events` **(new, one page)** | new | Yes, for the seven verified windows | Verified dates, and a prefilled trip form |
| travel alone to {city} | `/d/{slug}` | as above | Partly | Says plainly nobody is there yet when nobody is |
| backpacking {city} alone | `/d/{slug}` | as above | Partly | Same |

**The honest conclusion:** we have pages that satisfy every one of these intents and no ranking for
any of them. Building more pages would not change that; the pages are not the constraint, the
absence of any link from anywhere is. That is a distribution problem, which is what the rest of
`docs/ACQUISITION_*` is about.

## What I recommend, in order

1. **Done today: the shared social component on place pages, guides and posts.** Highest value, no
   new URLs, no SEO risk.
2. **Done today: one events page**, built from the campaign windows that already exist, linked from
   the header, footer and explore in the same commit, listed in the existing sitemap. One URL.
3. **Wait for the title test.** First honest read is 2026-09-29. Do not expand it, do not retitle
   anything else, and do not draw conclusions before then.
4. **Check the facts on the four place pages ranking inside position 10.** They rank because they
   answer a factual question; the only thing that could lose that is the fact going stale.
5. **Do not chase the tax and price queries.** They convert into nothing this product does. The San
   Francisco article is the best ranking thing here and it is also the least relevant, which is worth
   remembering before anyone suggests writing more like it.

## What I did not do, and why

* **No new pages.** The intent table above shows every intent already has a URL.
* **No sitemap architecture change.** One static path was added to the existing list because a page
  that exists and is linked should be listed; nothing else moved.
* **No robots, canonical or noindex change.**
* **No expansion of the title experiment.**
