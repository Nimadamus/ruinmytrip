# Route map and change log

This file exists because the brief for the 2026 repositioning required that **no public URL is
deleted or renamed without being documented first**. It is the record of that.

## Summary of the repositioning

RuinMyTrip moved from "travel social network" to **travel-warning and trip-risk platform**
("Know what could ruin your trip before you book it"). The change is almost entirely **additive**:
a new `warnings` entity, a risk-report layer on destinations, a watchlist/alert system, an
editorial landing-page system, and a much larger admin.

**No public route was deleted. No public route was renamed.**

---

## Routes REMOVED

None.

## Routes RENAMED

None.

## Routes whose *handler* changed (URL preserved)

| Route | Before | After |
|---|---|---|
| `GET /` | Social homepage: trending destinations, trip stories, reviews, meetups | Risk-first homepage: hero + destination search, popular destinations with warning counts, trending warnings, the ten warning categories, how-it-works, latest traveler reports, email-alert CTA |
| `GET /d/{slug}` | Destination page led by community rating, trip stories, reviews, meetups, who's going | **Destination risk report**: overall risk level and summary, is-it-worth-visiting, the 13 reviewed risk sections, traveler warnings, FAQ, related destinations, submit CTA. The entire previous community layer (trips, reviews, tips, photos, guides, meetups, who's going) is **retained** further down the page and still linked. |
| `GET /search` | FTS over destinations, trips, guides, reviews, blog, collections, people | Same, **plus** warnings, editorial guide pages, named subjects (hotels/attractions/operators/neighbourhoods) and airport codes; adds exact-phrase (`"..."`) and typo-tolerant "did you mean" |
| `GET /register` | "Join RuinMyTrip — build your traveler profile" | "Save destinations and receive important warnings before your trip", with the concrete benefit list beside the form |
| `GET /sitemap.xml` | Destinations, trips, guides, reviews, collections, blog, tags, profiles | Same, **plus** `/warnings`, the ten category pages, every approved warning permalink, every destination warning list that has content, every published editorial guide page, `/warning-guides`, `/alerts` |
| `GET /admin` | The abuse-report queue | Admin **overview** (queues, coverage gaps, content stats). The original abuse-report queue kept its exact handler and moved to `/admin/reports`. `/admin` is behind `require_role('admin','mod')` and has never been public or indexed, so this is an internal change only — but it is recorded here because a moderator's bookmark changes meaning. |

## Routes ADDED

### Warnings (the core entity)
```
GET  /warnings                          every approved warning, filterable
GET  /warnings/{category}               one of the ten categories (own copy + worst-affected destinations)
GET  /warning/new        POST /warning/new
GET  /warning/{id}/edit  POST /warning/{id}/edit
POST /warning/{id}/delete
POST /warning/{id}/helpful              one vote per person, toggleable
GET  /w/{id}/{slug}                     warning permalink (canonicalising redirect on a wrong slug)
GET  /w/{id}/respond     POST /w/{id}/respond    business right-of-reply (no account needed)
POST /outdated                          "report outdated information" (no account needed)
GET  /d/{slug}/warnings                 one destination's full, filterable warning list
POST /destination/follow                follow a destination without dates
```

### Trip watchlist, alerts, dashboard
```
GET  /dashboard                         member dashboard (trips / reports / saved / email settings)
POST /watchlist/add
GET  /watchlist/{id}/edit  POST /watchlist/{id}/edit
POST /watchlist/{id}/delete
GET  /alerts             POST /alerts/subscribe
GET  /alerts/confirm                    double opt-in
GET  /alerts/unsubscribe                one click, no login
```

### Editorial guide pages + monetization
```
GET  /warning-guides                    index of published editorial guides
GET  /{slug}                            THE RESOLVER — registered LAST in the table
GET  /go/{slug}                         the only outbound path for a partner link
GET  /api/suggest?q=                    autocomplete JSON (no auth, no write, cacheable)
```

### Admin
```
GET  /admin                             overview            (was: report queue)
GET  /admin/reports                     abuse-report queue  (unchanged handler)
GET  /admin/warnings                    moderation queue
POST /admin/warnings/{id}/moderate      approve/reject/revise/verify/dispute/feature
GET  /admin/destinations
GET  /admin/destination/{id}            POST /admin/destination/{id}
POST /admin/destination/{id}/section    upsert one risk section
POST /admin/destination/{id}/faq
GET  /admin/pages   GET /admin/page/new   GET /admin/page/{id}
POST /admin/page    POST /admin/page/{id}/delete
GET  /admin/responses      POST /admin/response/{id}
GET  /admin/outdated       POST /admin/outdated/{id}
GET  /admin/alerts
GET  /admin/affiliates     POST /admin/affiliate    POST /admin/affiliate/{id}/delete
GET  /admin/users          POST /admin/user/{id}
GET  /admin/analytics
GET  /admin/homepage       POST /admin/homepage
```

---

## The `GET /{slug}` resolver — why it is safe

`['GET', '#^/(?<slug>[a-z0-9][a-z0-9\-]{3,90})$#', 'landing_page']` is the **last** entry in the
route table in `public/index.php`. Three things keep it from being a hazard:

1. **It is last.** Every specific route above it wins. It can never shadow an existing page.
2. **It resolves only real rows.** `landing_page()` looks the slug up in `seo_landing_pages`; a
   slug with no published row 404s exactly as before. It cannot render a stub for an arbitrary
   path, and cannot be used to probe for internal paths.
3. **Collisions are rejected at write time.** `rmt_reserved_slugs()` in
   `app/controllers_admin.php` lists every first-segment path the router owns, and
   `admin_page_save()` refuses to save a page whose slug is on it — so an unreachable page can
   never be created in the first place.

**When adding a new top-level route, add its first path segment to `rmt_reserved_slugs()`.**

---

## Redirect and canonicalisation behaviour

* `/w/{id}` and `/w/{id}/wrong-slug` **302** to the canonical `/w/{id}/{slug}` when the warning is
  approved. Unapproved warnings do not redirect (the canonical URL is not public yet).
* `/dashboard`, `/warning/new`, `/warning/{id}/edit`, `/watchlist/*` **302** to `/login` when
  logged out, via the existing `require_login()`.
* `/admin*` returns **403** (not a redirect) for a signed-in non-moderator.

## robots / indexing

No `noindex` is emitted anywhere. Draft landing pages and unapproved warnings are protected by
**access control and a 404**, never by a robots directive — the same rule the site already
applied to draft reviews.
