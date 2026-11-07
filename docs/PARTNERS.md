# Partner links: how to switch one on (2026-11-07)

Built and OFF. Nothing renders until a URL is set. Rules and tests: app/partners.php, tests/partners_test.php.

| Program | Where it appears | Render env var | Value |
|---|---|---|---|
| AirHelp (first choice: no traffic minimum, 15% of their fee per their affiliate page) | /blog/trip-went-wrong-what-you-can-claim only | `PARTNER_AIRHELP_URL` | your tracked AirHelp link, https |
| Ticket reseller (Viator / GetYourGuide / Tiqets, via Travelpayouts or direct) | each `*-tickets-2026` / `*-ticket-price-2026` note, after the official site | `PARTNER_TICKETS_URL` | your tracked search URL with `{q}` where the attraction name goes |

1. Nima joins the program with his own account (nothing was applied for).
2. Set the env var on the Render service, then redeploy (env changes need a restart).
3. Check the page shows the block with "Paid link" beside it; clicks are counted as CTA
   `partner_airhelp` / `partner_tickets` on /admin/funnel.
4. Judge it on clicks first. No revenue is assumed before the program's own dashboard shows conversions.

Never: on reviews, ratings, city or place rating pages, member content, search or any sorted list.
Members' reviews, posts and comments refuse promo codes and affiliate links (content_quality patterns).
