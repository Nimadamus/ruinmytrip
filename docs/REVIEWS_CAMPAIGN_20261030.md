# Reviews campaign kit (reviews1030), prepared 2026-10-30

Goal: the first 10 genuine traveler reviews in 4 weeks, from real people about places they went.
Baseline (30 days to 10-29): ~70 verified humans, 1 signup, 0 member reviews, GSC ~700 impressions/28d.

Hard rules for every item below
* No fabricated reviews, testimonials, members or activity. Empty pages stay empty.
* No spam: reply only where somebody asked a question the link answers; never cross post the same text.
* Published copy has no dashes of any kind (URL paths are the only exception, they are addresses, not prose).
* Outreach is signed "Claude, the AI agent that runs RuinMyTrip". Never as Nima.
* Reddit: many travel subs ban AI written text and self promotion. Anything posted on Reddit is
  written or rewritten by a human under their own account, or not posted at all (see channel table).
* Facebook: nothing before the Page restriction lifts (about 2026-11-08), and only after Step 0 in
  `C:\Users\BL\rmt_social\FB_RESUME_AFTER_RESTRICTION.md` passes. Instagram is PARKED (later list only).
* This doc is a plan. Nothing here has been posted or sent.

## 1. Flow audit (live site, headless chromium, desktop 1366 and phone 390, read only, no submits)

All five pages returned 200, no console errors, no failed requests, no broken images, no horizontal
overflow at either width.

| Entry | Steps to a published review | Sign up wall before writing? | Notes |
|---|---|---|---|
| Place page `/p/vasa-museum-stockholm` | 1 tap a star (above the fold on phone, y~450) > 2 write 40+ chars, Post > 3 email, password, 16+ tick > 4 confirm email, then live | No | Best path. Place is locked in, rating preselected, headline and extras folded under "Add more detail". Clear copy: "No account needed to start", "Yours would be the first on this page". |
| City page `/d/lisbon-portugal` | "Rate a place in Lisbon" (y~354 phone) or per place "Rate" chips, then as above | No | Good. Bottom "Share your experience" sits at y~8000 on phone, after a 10,400 px page; fine as a second ask. |
| `/ruined` | type one sentence (+ optional city) > review form with the sentence prefilled > pick destination, type, name, stars, body > account > confirm | No | Strong hook question, but it lands on the unbound form, which then asks for 4 more decisions. Page is 28,400 px tall on phone. |
| `/review/new` (unbound) | destination typeahead > subject type > place name > stars > body (40 min) > Post > account > confirm | No | Most friction: 5 required decisions before writing. Destination input and several optional fields have no programmatically associated label (screen readers). |
| `/contribute` | search a place > pick from suggestions > bound form | No for listed places; YES for a missing place: "Sign in to suggest a place" | A visitor whose place is not in the catalog hits a login wall and the review is lost. |

Ranked friction findings
1. Email confirmation gate: an email signup review is held until the confirm link is clicked
   (`plan_first.php`, "goes live the moment you confirm"). Every unconfirmed signup is a lost review.
   Google sign in (which skips it) is not enabled on the live join page.
2. Unbound form (`/review/new`, `/ruined` landing, every generic nav "Rate a place" link) asks for
   destination, type and name before the writing. Campaign links must deep link to a bound place or
   at least `?destination=<id>&about=destination` to skip those.
3. `/contribute` missing place path is a sign in wall (`views/contribute.php` line 97).
4. Body minimum 40 characters is fine; there is a live counter.
5. Accessibility: unlabeled inputs on the review form (destination typeahead, date, extra textareas, aspect selects).

## 2. Review request mechanisms

Existing
* On page asks: place page "Been to X?" stars (`views/_rate_place.php`), city page "Rate a place in X"
  plus per place Rate chips (`views/_rate_dest.php`, `_city_top_places.php`), "Write the first review",
  blog "Been to X?" stars (`about=destination`), the "What ruined your trip?" box on home, blog and
  `/ruined` (`views/_ruined_ask.php`), travel map "review the cities you colored" chips
  (`views/travel_map.php` line 72), welcome page and `/start` links, post publish "Review another place in X"
  (`views/review_show.php` line 34).
* Write first, join after: guest reviews are held in session and server side (`app/review_drafts.php`)
  and published at account creation (`app/plan_first.php`).
* Post trip ask: `trip_over` notification the day after a trip ends (`app/lifecycle.php`).

Missing or broken (high value)
* A. `trip_over` is in app only. No email, and the notification links to the trip page, not the review form.
* B. The lifecycle job only runs when a signed in member opens `/feed` (`app/controllers.php` line 1407).
  With ~1 member it almost never runs, so even the in app ask rarely fires.
* C. No email footer postal address (`app/mail.php` `rmt_mail_layout`), which CAN-SPAM needs on any
  marketing email, including the one off ask below.
* D. Forum sources are not in `RMT_ACQ_SOURCES` (`app/acquisition.php`), so tripadvisor, flyertalk,
  ricksteves, fodors, cruisecritic all collapse to `other`. Medium `page` (used by the FB resume plan)
  collapses to `other` too.
* E. `/contribute` missing place sign in wall (finding 3).

Code changes (described, NOT implemented)

A. Post trip email (`app/lifecycle.php`, `rmt_lifecycle_run`, the `$over` loop)
```php
// select: add d.name dest_name, d.id dest_id via LEFT JOIN destinations d ON d.id = t.destination_id
foreach ($over as $t) {
    q_run('INSERT INTO notifications ...');            // unchanged
    $made++;
+   if (function_exists('rmt_notify_email_direct') && !empty($t['dest_name'])) {
+       rmt_notify_email_direct((int) $t['user_id'],
+           'How was ' . $t['dest_name'] . '?',
+           'Welcome back. Two or three sentences about what nearly ruined ' . $t['dest_name']
+             . ' is the thing the next traveler reads first.',
+           '/review/new?destination=' . (int) $t['dest_id'] . '&about=destination&src=trip_over'
+             . '&utm_source=email&utm_medium=email&utm_campaign=trip_over',
+           'your trip to ' . $t['dest_name'] . ' just ended');
+   }
}
```
and in `views/notifications.php` (trip_over branch, ~line 118) point `$href` at the same review URL
instead of the trip page. Existing caps (verified, opted in, 1/hour, 6/day) already apply.

B. Run the lifecycle job on a request a robot makes anyway: in `app/controllers.php` `sitemap()`
(line ~6538) add `if (function_exists('rmt_lifecycle_if_due')) rmt_lifecycle_if_due();` next to
`rmt_seo_flush_if_due();`. Keep the feed call. Same throttle (30 min site wide), same idempotence.

C. CAN-SPAM footer: `app/mail.php` `rmt_mail_layout` append
`'<p style="color:#8895a3;font-size:12px">RuinMyTrip, ' . e((string) cfg('mail_postal_address', '')) . '</p>'`
when the config value is set; add `mail_postal_address` to `config.sample.php`. Nima supplies the address.

D. `app/acquisition.php`: add `'tripadvisor','flyertalk','ricksteves','fodors','cruisecritic','travelfish'`
to `RMT_ACQ_SOURCES` and `'page'` to `RMT_ACQ_MEDIUMS`. Until then use the fallback in section 5.

E. `views/contribute.php` line 97: replace the login link with a guest path,
`url('review/new?src=contribute_missing&subject=' . rawurlencode($prefillName))`, and let
`review_new_form` prefill `subject_name` from `?subject=` (the unbound form already accepts a free
text place name; the guest hold already works).

## 3. Channel evaluation

Rules were read from public sources where reachable; Reddit's own rule pages refuse automated reads,
so subreddit rules below come from mirrors and must be rechecked in the sidebar before any post.

| Channel | Rules found | Viable? | How |
|---|---|---|---|
| r/solotravel | Blog/vlog links, surveys and self promotion removed outside the weekly general thread; AI itineraries banned as low effort | Comments only, human written, no link unless asked | Answer "what went wrong" questions with real advice; link only if someone asks where to post a warning |
| r/travel | Self promotion not allowed (sidebar to confirm); AI text widely banned on travel subs | No posts. Comments only, no link | Same as above |
| r/travelpartners | Buddy posts; site links usually removed | Not for reviews | Belongs to the buddy plan, not this one |
| City subs (r/Lisbon, r/stockholm, r/AskEurope) | Varies, most ban promotion | Low | Skip in this campaign |
| Quora | Disclose affiliation in the answer; links only when relevant | Yes | Answers to "worst tourist trap in X" / "what went wrong on your trip" with a disclosed link. Signed as Claude |
| X (@ account if one exists) | No rule against own links | Yes, low reach | 2 posts a week asking one question, link to `/ruined` |
| Tripadvisor forums | Links allowed only if useful and not promotional; solicitation banned; accounts get disabled for self promotion | No links | Do not ask for reviews there. Read only for which places people complain about |
| Rick Steves forum | Moderators remove posts that promote blogs or guides | No | Skip |
| FlyerTalk | Promoting a commercial blog goes to moderator review | No | Skip |
| Lonely Planet Thorn Tree | Closed permanently (2021) | No | n/a |
| Facebook groups (after 11-08) | Group by group; many allow a link in a reply that answers a question | Yes, selectively | Only groups whose rules allow it, one reply per thread, after the Page is healthy |
| Facebook Page (after 11-08) | Own page | Yes | Item 6 in the FB resume queue already is a review invitation |
| Existing members, email | Own list; opted in, verified only | Yes, tiny | One off email below |
| Friends and family wave (REVIEW_SEEDING_KIT.md) | Own contacts | Yes, best yield | Nima's call; links in `~/rmt_campaign1/FRIEND_LINKS.md` |
| Instagram | PARKED by owner | Later | Revisit only when Nima raises it |

## 4. Ready copy (value first, no dashes, signed as Claude where it is outreach)

### Quora answer (example question: "What is the most overrated tourist attraction in Lisbon?")
> I run the travel site RuinMyTrip as its AI agent, so weigh this with that in mind.
>
> Tram 28 is the one most people regret. The ride is lovely in theory, but the queue at Martim Moniz
> often runs over an hour in summer and the cars are packed shoulder to shoulder, which is also why
> pickpockets like it. Two easy fixes: board at the other end near Campo de Ourique early in the
> morning, or walk the same Alfama streets and take tram 12 for a shorter loop.
>
> If you have been and something went wrong for you there, I collect exactly that kind of warning
> here, one or two sentences is enough: [link]
>
> Claude, the AI agent that runs RuinMyTrip

Rule for every Quora answer: real, current, specific advice first (checked against an official
source that day), disclosure line at the top, link last, one answer per question, never a pasted template.

### Reddit comment (human writes or rewrites it; posts only where a thread asks)
No ready text here on purpose: a Reddit comment has to be the poster's own experience in their own
words, and Claude cannot have one. Shape to follow: answer the question asked, one concrete tip from
a trip the poster really took, no link.

No link by default. If somebody asks "is there anywhere that collects these warnings", reply with
the `/ruined` link and say plainly it is a site the commenter's friend runs (if true) or skip.

### X posts (two a week)
* "What ruined a trip for you? One sentence. The worst ones save somebody else a day. [link]"
* "Been to the Vasa Museum? Nobody has reviewed it on RuinMyTrip yet. The bad parts are the useful parts. [place link]"
* "Before you book Tram 28: what would you tell a friend? [Lisbon link]"
Signature in bio: "Run by Claude, an AI agent." Never claim a review count the site does not have.

### Facebook (after 2026-11-08 only, after Step 0 passes)
Page post (matches queue item 6):
> Quick question for anyone who has traveled this year: what nearly ruined the trip? The queue
> nobody mentioned, the fee at the door, the hotel that lied about the shower. Tell us in one or two
> sentences and it goes on the page for that place, so the next person sees it before they book.
> [link]
> Claude, the AI agent that runs RuinMyTrip

Group reply (only where the group allows links and somebody asked about that city):
> {One sourced, current fact that answers the question, checked on the official site that day, e.g.
> "Timed tickets are released on {date} on the official site, so book first and plan the day around
> the slot."} If you go, I would love a line on what surprised you, it helps the
> next person: [city link]
> Claude, the AI agent that runs RuinMyTrip

### One off email to existing members (opted in only)
Audience: `users.status='active' AND email_verified_at IS NOT NULL AND COALESCE(profiles.digest_opt_out,0)=0`.
Send only after change C ships (postal address in the footer). Sent once, never repeated to the same person.

Subject: One question about your last trip

> Hi @{username},
>
> RuinMyTrip is still small, and the pages that help people most are the ones with an honest line
> from somebody who was there. You told us about {last_destination_or "a trip"}, so a quick ask:
> what nearly ruined it?
>
> Two or three sentences is plenty. The bad parts are the useful parts.
>
> [Write it here]({bound review URL with UTM})
>
> Thanks,
> Claude, the AI agent that runs RuinMyTrip
>
> You are getting this because you have a RuinMyTrip account and have not turned off emails.
> [Turn these off]({rmt_unsubscribe_url})
> RuinMyTrip, {POSTAL ADDRESS PLACEHOLDER}

CAN-SPAM checklist: honest subject (yes), sender identified (yes), postal address (placeholder until
change C), working unsubscribe honored within 10 business days (existing `rmt_unsubscribe_url`), not
deceptive "Re:" or "Fwd:" (yes).

## 5. Link scheme (utm_campaign=reviews1030)

Best landing URLs, in order:
1. A place page, so the visitor taps a star: `https://ruinmytrip.com/p/<place-slug>`
2. A bound city review: `https://ruinmytrip.com/review/new?destination=<id>&about=destination` (Lisbon id 2)
3. General: `https://ruinmytrip.com/ruined` (strong hook, but lands on the unbound form)

Pattern: `?utm_source=<source>&utm_medium=<medium>&utm_campaign=reviews1030&utm_content=<where>`
(utm_content uses underscores, max 40 chars.)

| Channel | utm_source | utm_medium | utm_content example |
|---|---|---|---|
| Quora | quora | post | lisbon_tram28 |
| Reddit | reddit | comment | solotravel_common_room |
| X | x | post | x_ruined_q1 |
| Facebook Page | facebook | post | page_invite_1 (`page` medium collapses to other until change D) |
| Facebook group | facebook | group | grp_<groupname> |
| Member email | email | email | members_1 |
| Friends wave | friends | dm | friends_<initials> |
| Forums (until change D) | other | comment | tripadvisor_<thread> |

Example: `https://ruinmytrip.com/p/vasa-museum-stockholm?utm_source=quora&utm_medium=post&utm_campaign=reviews1030&utm_content=stockholm_museums`

Internal checks use `utm_content=selfcheck` so they are excluded.

## 6. Measurement

Attribution sticks to the session (`rmt_acq_current()`), and every `rmt_track` row in
`contribution_events` stores `acq_source/acq_medium/acq_campaign/acq_content`.

Funnel events already emitted (app/controllers.php, app/plan_first.php):
`review_cta_click` > `review_signup_required` (guest opened form) > `review_form_start` >
`review_draft_restored` > `review_submit_attempt` > `review_held_for_join` (guest) > `plan_signup_view` /
`join_view` > `review_signup_completed` / `review_login_completed` > `review_return_after_auth` >
`review_verification_required` > `review_publish_success` / `review_publish_failure` (reason).

KPI rollup (`app/kpi.php`): `review_starts`, `review_held`, `reviews`, `accounts`; snapshot via `scripts/kpi_snapshot.py`.

Weekly query (read only):
```sql
SELECT acq_source, acq_content, event, COUNT(*) n
  FROM contribution_events
 WHERE acq_campaign = 'reviews1030'
   AND event IN ('review_form_start','review_held_for_join','review_verification_required',
                 'review_publish_success','review_publish_failure')
 GROUP BY acq_source, acq_content, event ORDER BY acq_source, n DESC;
```
Success: 10 `review_publish_success` with campaign reviews1030 or friends by 2026-11-27. Watch the
gap between `review_held_for_join` and `review_publish_success`: that is the email confirmation loss
(finding 1). Drop any channel with zero form starts after two weeks.

## 7. Four week schedule

| Week | Dates | Items |
|---|---|---|
| 1 | Oct 31 to Nov 6 | Nima decides on code changes A to E. Friends wave (Nima). 3 Quora answers (Lisbon, Stockholm, one other city with places). 2 X posts. Reddit: human comments only where asked, no links. |
| 2 | Nov 7 to Nov 13 | Nov 8 or later: FB Step 0 check. If clear, resume FB queue items 1 and 2. 3 Quora answers. 2 X posts. Member email once change C is live. First weekly query. |
| 3 | Nov 14 to Nov 20 | FB queue items 3 and 4, then item 6 (review invitation). Up to 3 FB group replies in groups whose rules allow links. 3 Quora answers. 2 X posts. Weekly query; drop dead channels. |
| 4 | Nov 21 to Nov 27 | FB queue item 5 and one more Page review ask with a different question. 3 Quora answers. 2 X posts. Final query; write results to KPI notes. |

Later (not in this campaign): Instagram (parked), Cruise Critic roll calls, paid test only after 25 members.

Sources checked 2026-10-30: r/solotravel rules via mirror (r-weld.vercel.app/r/solotravel), Tripadvisor
Content and Community Guidelines (tripadvisor.com Trust pages), Rick Steves forum moderator threads
(community.ricksteves.com), FlyerTalk trip report rules (flyertalk.com/forum/29002463-post2.html),
Quora disclosure guidance (quoraadsupport.zendesk.com/hc/en-us/articles/360029041832), Thorn Tree closure
(phocuswire.com, travelweekly.com).
