# First 100 members: the plan we execute

Written 2026-09-25 (Pacific). This replaces the "what to do next" parts of the older acquisition docs;
they stay as research. The product is frozen apart from bug fixes while this runs.

## What we have, measured

| Asset | State | What it has produced |
|---|---|---|
| Facebook Page "Ruin My Trip" (id 61594217710086) | Live since 2026-09-16, admin is the Badri profile, 0 followers, about 10 posts | 10 Facebook sessions in 7 days, 0 human. A Page with no followers reaches nobody |
| Instagram | No account | |
| TikTok | No account. Ten video scripts in `docs/ACQUISITION_VIDEO.md` | |
| Reddit | Blocked from this machine (403). Only login is u/TrustMyRecord (betting brand, karma 1). Unusable | |
| Facebook groups | Ten read, none allows unapproved promotion. Let's Go Solo admin asked 2026-09-16, no reply | |
| Google | 3 clicks in 7 days (Search Console, read 2026-09-25) | The 1,696 "search" landings in the same week are almost all automated |
| Content bank | 21 audience question posts, `docs/social/content_bank.json` | |
| Post kit | `python scripts/social_kit.py --start YYYY-MM-DD` renders carousels, copy and a schedule | |

## The route, fastest first

1. **Your own network, this week (members 1 to 10).** Ten real people who have a trip coming up post
   it. Send them `https://ruinmytrip.com/plan?utm_source=referral&utm_medium=dm&utm_campaign=friends`.
   Nothing else puts real trips on the site as fast, and real trips are what make the next visitor
   stay. No fake trips, no house trips.
2. **TikTok, starting the day the account exists (10 to 50).** It is the only platform that shows a
   brand new account to strangers. Photo carousels from the kit, five a week, no filming needed.
3. **Instagram, linked to the Facebook Page (10 to 50).** Same carousels, posted from Meta Business
   Suite together with the Facebook post, so it costs one scheduling pass a week.
4. **Facebook groups from your personal profile, with disclosure (25 to 100).** Only groups whose
   pinned rules allow it or whose admin says yes. The drafts are in `docs/ACQUISITION_FACEBOOK.md`.
5. **Reddit, only from an established personal account of yours (50 to 100).** r/solotravel and
   r/travel do not allow self promotion; the honest use is answering questions with no link and the
   site in the profile. Buddy request subreddits may allow a post; their rules have to be read in a
   browser first because this machine cannot load Reddit.
6. **Member referrals (50 to 100).** Every trip page already has invite and share. Once there are 25
   members, their shares are the cheapest channel we have, and they are measured as `utm_medium=share`.

## Milestones and what each one needs

| Members | Comes from | Done when |
|---|---|---|
| 10 | Friends and family with real upcoming trips | 10 accounts, 5 public trips with dates |
| 25 | TikTok and Instagram carousels, first two weeks | Some city has two travelers on overlapping dates |
| 50 | Groups that allow it, plus a Reddit presence | Engaged to member rate readable per channel |
| 100 | Whichever channel the table says works, plus referrals | Two travelers connect through the site |

## The weekly routine

* **Monday:** run `social_kit.py` for the next 7 days. Review the slides.
* **Monday, one browser pass:** schedule the week in Business Suite (Facebook and Instagram together)
  and in TikTok Studio (it schedules up to 10 days ahead). About 30 minutes.
* **Daily, 5 minutes:** answer comments on the posts. Comments are the whole point of question posts.
* **Friday:** read the by channel table on `/admin/funnel` (7 days). Keep what produces engaged
  visitors; drop what does not after two weeks.

## What can be automated, and what it needs from you

| Step | Now | Fully automatic later |
|---|---|---|
| Making the posts | Automatic (`social_kit.py`) | A scheduled task, headless |
| Facebook and Instagram posting | I schedule a week at a time in Business Suite when Chrome is signed in as the Page admin | A Page access token from a Meta app you create; then a headless daily task posts through the Graph API. Your call, it is a credential |
| TikTok posting | I schedule in TikTok Studio when you are signed in | TikTok's posting API needs an app review; not worth it before the channel proves itself |
| Reddit | Manual, by you | Not automated, on purpose |

## Measurement

`/admin/funnel` has a by channel table: search, facebook, instagram, tiktok, reddit, direct, referral,
each with landed, engaged, trip form opened, acted, signup started, signed up, first contribution
and came back. Facebook posts carry a per post `utm_content`; Instagram and TikTok carry one bio link
each, so they are read per platform. No channel is judged before 50 engaged visitors.
