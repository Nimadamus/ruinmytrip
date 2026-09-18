# What happens after somebody arrives

> **Update 2026-09-18:** all three gaps below are closed in code (match email in `app/matching.php`, connect request and acceptance emails in `app/controllers.php`). A travel buddy post written before the email is confirmed is now held and published on confirmation, like trips.

An audit of every moment between a signup and a connection, and what reaches the person at each one.
Read from the code on 2026-09-16. **Nothing here was built; three gaps are documented with the exact
change each needs, because sending mail to real people is outward facing and is your call.**

## What already reaches a person by email

Seven things, all through `rmt_notify_email_direct()`, which is capped per person per day so a busy
plan cannot fill an inbox:

| Moment | Email | Where |
|---|---|---|
| Somebody replied to your post | yes | `app/controllers.php` |
| You were mentioned | yes | `app/mentions.php` |
| **You received a message** | yes | `app/messages.php` |
| You were invited to a trip | yes | `app/trip_members.php` |
| Somebody wants to join your activity | yes | `app/controllers.php` |
| You are in, an activity accepted you | yes | `app/controllers.php` |
| An activity you joined was cancelled | yes | `app/controllers.php` |

## The three gaps, and why each one matters

All three write an in-app notification and send nothing. On a site with a daily habit that is fine.
On a site somebody signed up to once, an in-app notification is a message left in a room nobody
returns to.

### 1. Somebody's dates landed on yours

* **Where:** `app/matching.php`, the insert into `notifications` at the end of `rmt_match_notify()`.
* **Why it matters most of the three:** this is the entire product firing. A traveler posted dates
  that overlap yours in a city you are going to, which is the thing you joined for, and today you
  only find out if you happen to come back.
* **The change:** one call beside the existing insert, subject "Somebody's dates overlap yours in
  {city}", linking to `/matches`. The per person daily cap already exists, and `RMT_MATCH_NOTIFY_MAX`
  already stops a popular city becoming a mailing list.
* **Risk:** low. It is triggered by a real event about the recipient, and it is the least spammy
  email this site could send.

### 2. Somebody asked to connect

* **Where:** `app/connects.php`, `rmt_connect_request()`.
* **Why it matters:** a connection request is a person waiting on an answer. Unanswered because
  unseen is the worst possible outcome for both of them, and messaging is gated behind acceptance, so
  an unseen request blocks the whole conversation.
* **The change:** one call, subject "Somebody wants to connect on a trip", linking to `/matches`.

### 3. They accepted

* **Where:** `app/connects.php`, `rmt_connect_decide()` on acceptance.
* **Why it matters:** acceptance is the moment messaging opens. Both sides said yes and neither is
  told, so the mutual opt in we built carefully ends in silence.
* **The change:** one call to the requester, subject "You can message each other now".

## What I deliberately do not recommend

* **No welcome sequence.** A drip campaign to five accounts is theatre.
* **No digest.** There is nothing to digest yet, and an empty weekly email teaches people to ignore
  the sender before there is anything worth reading.
* **No re-engagement email.** Asking somebody to come back to a page that says nobody is there is
  worse than silence.
* **No marketing list.** Every email above is triggered by a real event about the recipient. That
  distinction is the whole difference between a product email and spam, and it is worth keeping even
  when the numbers are small.

## The rest of the path, which is already sound

| Moment | What happens now | Verdict |
|---|---|---|
| Signup from a campaign | Lands back on the trip form with the city and both dates filled | Good, verified end to end |
| Signup page itself | Names the city and the dates they chose | Fixed 2026-09-16 |
| Trip submitted before the address is confirmed | **Held and written the instant it is confirmed**, not discarded | Good, and better than most sites |
| Email confirmed | Sent to the prefilled trip form, not a welcome page | Good |
| Reaches the feed with no trip | The first card names the campaign window instead of asking where they are going | Fixed 2026-09-15 |
| First trip created | Lands on `/matches` with that trip highlighted | Good |
| No matches | Says so honestly, offers follow, ask, everyone in the city, invite, and asks what they were hoping to find | Fixed 2026-09-16 |
| A match appears | In app notification only | **Gap 1** |
| Connection requested | In app only | **Gap 2** |
| Connection accepted | In app only | **Gap 3** |
| Message received | Email | Good |

## In one line

The path from a link to a trip is solid and verified. The path from a trip to a connection is
silent, and those three one line changes are the difference between a network that works and one
where two people who wanted to meet never found out they could.
