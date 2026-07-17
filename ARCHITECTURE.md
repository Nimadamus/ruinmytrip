# RuinMyTrip — Architecture & MVP Plan

Brand: playful name, premium/trustworthy product. Isolated from TrustMyRecord/BetLegend/all other projects.

## 1. Hosting suitability (decision)
- Product = dynamic social network (accounts, DB, feeds, moderation, admin).
- **Namecheap shared cPanel is suitable for the MVP** via **PHP 8 + MySQL** (LiteSpeed/Apache, free AutoSSL, no build step).
- Not suitable for real-time websockets / heavy background jobs / large media at scale.
- **Plan:** ship MVP on shared hosting; offload media to object storage later; migrate app to VPS/cloud
  only when real-time features or traffic demand it — **domain stays at Namecheap** either way.

## 2. Information architecture (routes — all indexable unless auth-gated)
```
/                         Homepage (hero, search, trending, stories, reviews, meetups, guides, CTAs)
/explore                  Browse destinations (search + filters)
/d/{slug}                 Destination detail (guides, reviews, stories, who's-going)
/u/{username}             Traveler profile
/feed                     Social activity feed (auth)
/trip/new                 Create trip post (auth)
/trip/{id}/{slug}         Trip story detail
/reviews                  Reviews index
/review/new               Write a review (auth)
/guides                   Travel guides index
/g/{slug}                 Guide / itinerary detail
/meetups                  Public meetup listings (safety-first)
/meetup/{id}              Meetup detail
/going                    "Who's going" destination/date discovery (auth to appear)
/search?q=                Global search
/notifications            (auth)
/login /register /logout  Auth + age gate
/settings                 Profile + privacy controls (auth)
/admin                    Moderation dashboard (role=admin/mod)
/report                   Report content (auth)
/terms /privacy /guidelines /affiliate /safety   Legal + safety
/sitemap.xml /robots.txt
```

## 3. Database schema (see database/schema.mysql.sql + schema.sqlite.sql)
Core tables:
- **users** (id, username, email, password_hash, role[user|creator|mod|admin], birthdate, created_at, status)
- **profiles** (user_id, display_name, bio, home_city, avatar_url, cover_url, credibility_score, links_json)
- **destinations** (id, slug, name, country, region, lat, lng, summary, hero_url, category)
- **trips** (id, user_id, destination_id, title, slug, body, cover_url, visited_on, verified, status, created_at)
- **trip_photos** (id, trip_id, url, caption, sort)
- **reviews** (id, user_id, destination_id, subject_type[destination|hotel|restaurant|nightlife|attraction|tour|business],
  subject_name, rating 1-5, title, body, visited_on, verified, status, created_at)
- **guides** (id, user_id, destination_id, slug, title, summary, body, cover_url, premium, status, created_at)
- **follows** (follower_id, followee_id, created_at)  PK(follower,followee)
- **comments** (id, user_id, target_type, target_id, body, status, created_at)
- **likes** (user_id, target_type, target_id)  ; **saves** (user_id, target_type, target_id)
- **meetups** (id, host_id, destination_id, title, description, date_start, date_end, visibility, capacity,
  safety_ack, status, created_at)  — location = destination-level only, never precise coords
- **meetup_rsvps** (meetup_id, user_id, status)
- **going** (id, user_id, destination_id, date_from, date_to, visibility[public|followers|private], created_at)
- **notifications** (id, user_id, type, actor_id, target_type, target_id, read_at, created_at)
- **reports** (id, reporter_id, target_type, target_id, reason, details, status, created_at, resolved_by)
- **blocks** (blocker_id, blocked_id)
- **verifications** (id, user_id, target_type, target_id, method[gps_checkin|receipt|photo_exif], created_at)
- **sessions** handled via PHP session + remember-token table (auth_tokens).

Verification of "actually visited": MVP = manual/creator flag + optional geo check-in timestamp record
(coarse, city-level). No precise coordinates stored on public objects.

## 4. Authentication
- Email + password, `password_hash()` (bcrypt). CSRF tokens on all POST. Session cookies HttpOnly+SameSite=Lax.
- Age gate at registration: **16+ required** to register; **18+ required** to host/RSVP meetups (birthdate check).
- Roles: user, creator, mod, admin. Route guards via `require_role()`.
- Rate limiting on login/register (simple per-IP counter table) — Phase 2.

## 5. Moderation model
- Every user-generated object has `status` (published|pending|hidden|removed).
- `reports` queue -> /admin. Mods can hide/remove/restore, warn, suspend users.
- Blocklist hides content between users bi-directionally.
- Community Guidelines + automated banned-term filter on submit (Phase 2).

## 6. Privacy model
- Default: profile public, but **travel dates/plans are opt-in** and **destination-level only**.
- `going.visibility` = public|followers|private. No precise real-time location, ever.
- Meetups are public listings; exact address is never stored — organizers share specifics privately after connecting.
- Users control: who can message, who sees plans, block/report, delete account/content.

## 7. MVP scope (this build)
Foundation + running site: auth+age gate, homepage, explore, destination detail, profile, feed,
trip create/detail, reviews, guides, meetups (listing+safety), notifications shell, search, reporting,
admin shell, all legal/safety pages, SEO (sitemap/robots/canonical/OG/JSON-LD/breadcrumbs), seed data.

## 8. Phased roadmap
- **P1 (now):** server-rendered MVP above, SQLite local / MySQL prod, seed content, tested locally.
- **P2:** media uploads to object storage, banned-term filter, rate limiting, email verification, notifications delivery, "who's going" matching, saved/likes UX, richer search (full-text).
- **P3:** creator monetization (premium guides, affiliate booking links, promoted businesses), messaging, geo check-in verification, i18n, PWA.
- **P4:** scale-out (migrate app to VPS/cloud, CDN, real-time) if needed.

## 9. Files created/modified
New repo only. Key paths: `public/` (docroot: index.php front controller, router.php dev, .htaccess,
robots.txt, assets), `app/` (config, db, auth, csrf, helpers, seo, router table), `views/` (templates),
`database/` (schema.mysql.sql, schema.sqlite.sql, seed.php). No files outside this repo are touched.

## 10. Testing plan
- Local: `php -S localhost:8080 -t public public/router.php`, SQLite auto-seeded.
- Smoke: every route returns 200/expected; register->login->post trip->review->create meetup->report->admin resolve.
- SEO: robots.txt 200, sitemap.xml valid XML with only canonical 200 URLs, canonical+OG present, no noindex.
- Security: CSRF rejected without token; auth-gated routes 302 to /login; XSS-escaped output.
- Pre-deploy on host: PHP version >=8.1, pdo_mysql present, AutoSSL issued.

## 11. Deployment plan (NOT executed until approval)
1. Confirm cPanel account + docroot for ruinmytrip.com (addon domain or dedicated account) + server IP.
2. Create MySQL DB + user in cPanel; import `schema.mysql.sql`; set `app/config.php` (prod, gitignored).
3. Deploy repo to docroot via cPanel Git or FTP; point docroot at `public/`.
4. Enable AutoSSL (Let's Encrypt) for ruinmytrip.com + www.
5. Smoke test on a temp URL / hosts-file before DNS cutover.

## 12. Rollback plan
- Code: `git revert`/redeploy previous commit (staged commits keep every step reversible).
- DB: schema import is additive; keep a pre-change SQL dump before any prod migration.
- DNS (later): see below — revert A/CNAME to prior values; TTL 30–60 min.

## 13. Eventual DNS records (proposed — NOT applied yet)
Current live (Namecheap BasicDNS), to **preserve**: SPF TXT `v=spf1 include:spf.efwd.registrar-servers.com ~all`
and MX `eforward1-5.registrar-servers.com`.

Proposed after hosting confirmed (shared hosting -> point at cPanel Shared IP):
| Action | Type | Host | Value | Notes |
|--------|------|------|-------|-------|
| EDIT | A | @ | `<cPanel Shared IP>` | replaces parking 162.255.119.126 |
| EDIT | CNAME (or A) | www | `ruinmytrip.com.` (or Shared IP) | replaces parkingpage.namecheap.com |
| KEEP | TXT (SPF) | @ | v=spf1 include:spf.efwd.registrar-servers.com ~all | email forwarding |
| KEEP | MX | @ | eforward1-5.registrar-servers.com (10/10/10/15/20) | email forwarding |
Propagation: TTL ~30–60 min; global ~ up to a few hours. AutoSSL issues after A record resolves to host.
Applied only on explicit approval.
