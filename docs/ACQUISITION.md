# Getting the first real reviews

Internal. Written when the platform was finished and the community was not: 105 enriched places,
search, nearby, ranked destination modules, moderation, a measured funnel — and **zero traveler
reviews**. Every review on the site is the editorial account, clearly labelled as such.

This is no longer a coding problem. What follows is what we know, what is ready, and what needs a
decision from Nima.

## The rule that governs all of it

**No fabricated community activity. Ever.**

No generated accounts, no AI-written reviews, no editorial ratings recast as traveler ratings, no
reviews copied from Google, Yelp or Tripadvisor, no invented helpful votes, no padded counts. Zero
is temporary; a fake corpus is permanent, and it is the one mistake that would make everything
built here worthless.

Related, and just as firm: **never ask for a positive review.** Ask for an honest one. If an
incentive is ever offered it rewards contributing at all, regardless of the score given, and it is
disclosed on the page.

## What is ready to receive people

- `/contribute` — search a place you visited, go straight to writing. Falls back to a moderated
  suggestion queue when we do not have the place.
- Place, destination, browse, profile and homepage all carry one contribution surface each, tagged
  so the funnel can say which of them produces reviews rather than which gets clicked.
- Intent survives login **and** signup. A half-written review survives a refresh. Publishing
  without a confirmed email saves a draft rather than discarding the text.
- Moderation: report → queue → decide, with an audit log. A report changes nothing on its own and
  criticism is not a violation.
- `/admin/funnel` — the scoreboard and the drop-off, per surface, per window.

## Counts (read from production; refresh before deciding)

Registered accounts, how many are active, and how many have confirmed an email are on
`/admin/funnel` under "Who could be told". Read them there rather than trusting a number written
into this file, which will be stale the day after it is written.

## Email: what exists and what it does not

`app/mail.php` sends transactional mail (verification, password reset, the weekly digest), and
`profiles.digest_opt_out` plus `/unsubscribe` exist for the digest.

**There is no marketing consent flag and no campaign infrastructure, and that is fine.** A
one-off "you registered, here is what the site does now" message to existing registered members is
transactional-adjacent and defensible; a recurring campaign to the same list is not, without an
opt-in that does not currently exist. Nothing should be sent until Nima decides which of those
this is, and any send honours `digest_opt_out` and carries an unsubscribe link.

## Channels, roughly in order of how well they fit

1. **People who genuinely went.** Direct, individual invitations to travelers who have actually
   been to places we cover. Slowest per review, highest quality, and the only channel that
   produces a first review we would want to show anybody.
2. **Existing registered members.** One honest message about what the site is for. Cheap, already
   possible, and needs the decision above.
3. **Channels we already control.** The existing X account and any audience already reading the
   editorial pages. "Review a place you went to" pointing at `/contribute`.
4. **Travel communities that permit it.** Only where self-promotion is explicitly allowed, and
   never by dropping links into threads that did not ask. One community treated well beats ten
   treated as an audience. Note the standing rule already in force: never post to r/sportsbook,
   and treat every forum's rules as binding rather than negotiable.
5. **Organic search.** Already arriving on editorial pages; the contribution CTAs are now on those
   pages. Slow, compounding, and free.

## Milestones, and what each unlocks

| Reviews | What changes |
|---|---|
| **1** | Proves the whole path end to end in production. The place shows a traveler rating; the reviewer profile shows a contribution; the scoreboard moves. It must NOT qualify anything as "Top". |
| **10** | Enough to see the funnel's real shape — where people drop out, which surface works. |
| **50** | First places reach 3 reviews and the ranking modules start waking up honestly. |
| **250** | First destinations become community-ready (3+ rankable places from 3+ distinct people). |
| **500** | Category pages have enough behind them to be worth indexing. |

## What to watch when traffic starts

`/admin/funnel`, not intuition. The steps are CTA click → form → signup required → returned →
submit → published, split by whether the attempt began signed in. The signup branch is where an
anonymous attempt is most likely to be lost, and it is measured precisely so that it can be fixed
rather than guessed at.

## Do not do these

- Buy reviews, or trade them.
- Import a competitor's corpus.
- Loosen `RMT_TOP_MIN_REVIEWS` to make the ranking modules look populated. The dormancy is
  truthful; the modules waking up is the signal that the community is real.
- Run a campaign before moderation has been exercised on real content. The first thing a review
  site gets after asking for reviews is the reviews it did not want.
