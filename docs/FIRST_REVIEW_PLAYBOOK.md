# What to check when the first real reviews arrive

Internal. The activation path is tested (`tests/activation_test.php`, 45 assertions) but has never
run in production, because production has no traveler reviews. This is the checklist for the day it
does. Nothing here is to be triggered by creating an account or writing a review ourselves: these
states are observed when they happen, not manufactured.

## Review #1 — the first genuine traveler review

Read `/admin/funnel` and the affected pages, in this order.

| Check | Expect |
|---|---|
| Contribution event logged | `review_publish_success` for the journey, with the source surface it started from |
| Community reviews | **1** |
| Unique reviewers | **1** |
| Places with a review | **1** |
| Destinations with activity | **1** |
| Place page | the review appears with its author, date, and any aspect ratings given |
| Place rating | shows the true average of one review and a count of 1 — not presented as consensus |
| Reviewer profile | the review appears; contribution count is 1; any earned badge shows |
| Helpful | another signed-in traveler can mark it useful and the count appears (it stays hidden at zero) |
| Destination recent activity | the review shows in the destination's activity, where that module applies |
| **Ranking eligibility** | **still OFF.** The place must NOT appear in any "Top" module. `RMT_TOP_MIN_REVIEWS` is 3 |
| Structured data | the place page still validates; a single rating must not be advertised as an aggregate consensus |
| Moderation | the review is reportable, and a report changes nothing on its own |

If ranking eligibility switches on at one review, stop and treat it as a launch-severity bug: the
site would be asserting "Top" on the strength of one opinion.

## Review #2 — same person, a different place

The number of reviews is not the depth of the community. Expect:

- Community reviews **2**, unique reviewers still **1**
- Two places each with one review; neither rankable
- Nothing ranked anywhere

A second review from the same person must not move anything that is supposed to measure how many
different travelers we have.

## Review #3 — a third distinct reviewer on one place

This is activation. On the place that now has three reviews from three different people:

- It becomes rankable and appears in the destination's quality ranking
- The displayed average is the true average; only the ORDER uses the shrunken score
- The destination's "Top" modules stop being dormant for that category
- Photos, aspect averages (at `RMT_ASPECT_MIN_SAMPLE` = 3) and the rating breakdown all populate

Confirm the numbers on the place page match `/admin/funnel`. If they disagree, the aggregate is
wrong, not the display.

## When a review is hidden or removed

Every aggregate above must behave as though the review was never written — rating, count,
breakdown, recent activity, author totals, helpful totals and ranking eligibility — and restoring
must bring all of them back. This is asserted in the test suite; verify it once on real data the
first time a moderation action is taken.

## What not to do

- Do not create accounts or write reviews to trigger any of these states.
- Do not lower `RMT_TOP_MIN_REVIEWS` to make a module wake up sooner.
- Do not "help" the first review along by editing its text. A moderator fixing a typo is a
  moderator rewriting a traveler.
