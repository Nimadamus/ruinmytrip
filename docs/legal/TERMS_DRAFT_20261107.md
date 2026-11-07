# Terms of Service and Privacy: draft for legal review (2026-11-07)

**Status: DRAFT. Not published, not legal advice.** Written by Claude from how the site actually works,
so a lawyer reviews a complete draft instead of starting from the current MVP summary
(`views/legal/terms.php`, which itself says it "will be expanded before public launch"). Every
`[DECIDE: …]` is a choice only Nima or counsel can make. Nothing here changes the live pages.

Why this blocks launch: overnight stays, meetups and messaging between strangers are live (new stays
and host applications are paused since 2026-11-07, existing ones continue). The current Terms
mention hosting nowhere and do not say who runs the site or which law applies.

---

## A. Terms of Service (proposed structure and wording)

**1. Who we are.** RuinMyTrip is operated by `[DECIDE: legal name of the person or company,
registered address, country]`. Contact: `/contact` and `[DECIDE: a legal/notices email]`.

**2. Who can use it.** You must be at least 16 to create an account, and at least 18 to post a
trip for travel buddies, join or host a meetup, meet a local, or take part in an overnight stay.
We ask for your date of birth to check this and never show it. `[DECIDE: confirm 16 works for
every country you accept members from; some require parental consent below 16 or 18.]`

**3. Your account.** One person, one account. Keep your password private. You can delete your
account yourself at any time (Settings → Delete account); what happens to your content is set out
in the Privacy Policy.

**4. What you post.** You keep ownership. You give RuinMyTrip a worldwide, non-exclusive,
royalty-free licence to host, display, reproduce and distribute it on and to promote the service,
for as long as it is on the site, `[DECIDE: and for a limited time after deletion in backups (the
nightly encrypted backups keep 30 days, docs/BACKUP_RESTORE.md)]`. Do not post anything you do not
have the right to share, including other people's photos.

**5. Reviews and warnings.** A review must describe your own genuine experience. Prohibited: reviews
written for payment, gifts or discounts unless disclosed; reviews by or for a business about itself
or a competitor; fake or AI-generated experiences; and threats to leave a bad review to extract
something. Criticism is allowed, including one-star reviews. We do not remove a review because a
business dislikes it, and no business can pay to change a rating or its position. Our own researched
content is always labelled "Editorial" or "Official Review" and never counted in traveler ratings.
`[DECIDE/COUNSEL: align with the FTC rule on fake reviews and testimonials (16 CFR Part 465) and
the EU Omnibus Directive's review-verification disclosure: state what checks, if any, verify that a
reviewer visited.]`

**6. Meeting people: buddies, meetups, locals.** These connect strangers. RuinMyTrip does not check
identities: "Email confirmed" means only that. Nobody can message you until both of you agree, and
no address or live location is ever shown. You are responsible for your own decisions about whom to
meet and where. Meet somewhere public first and tell somebody where you are going. Read the safety
guidance at `/safety` and `/hosts-locals/safety`.

**7. Overnight stays (hosting).** `[COUNSEL: this section needs the most care.]` Stays are free. No
payment may be asked or offered for a stay, and RuinMyTrip takes none. RuinMyTrip is not a party to
any stay, does not inspect homes, and does not verify hosts' or guests' identities, homes, or right
to host (for example under a lease, a building's rules, or local short-term letting and guest
registration law). Hosts are responsible for complying with local law and their own lease or
insurance. Either side may cancel. Report any safety problem through the site and contact local
emergency services first. `[DECIDE: whether to require hosts to confirm they are allowed to host;
whether any jurisdictions are excluded; insurance position; minimum notice; what happens on a
report (suspension policy).]`

**8. Rules of conduct.** Follow the Community Guidelines (`/guidelines`). No harassment, threats,
hate, sexual content, solicitation or dating-style approaches, spam, scams, impersonation, sharing
other people's personal information, or attempts to get around blocks or the mutual-consent
messaging rule.

**9. Moderation.** Reporting creates a queue item; a moderator decides. We may hide or remove content
and suspend accounts that break these Terms or the Guidelines, and we record why. A suspended member
can ask for a review through `/contact`. `[DECIDE: response time you can actually commit to; EU DSA
statement-of-reasons and notice-and-action obligations may apply if you serve EU users.]`

**10. Copyright notices.** To report content that infringes your copyright, send a notice to
`[DECIDE: designated agent; for US safe harbour this must be registered with the US Copyright
Office (DMCA §512(c)(2)).]`

**11. Links and partners.** Some pages may link to third-party services; where we earn a commission
it is labelled "Paid link" next to it and explained at `/affiliate`. A commission never affects a
rating, a review, or the order of results.

**12. No warranties; liability.** The service is provided as is. Travel information, prices, rules
and scam warnings can change; check official sources before you rely on them. `[COUNSEL: limitation
of liability wording and carve-outs that are enforceable in the countries you serve; consumer law
in the EU/UK limits what can be excluded.]`

**13. Changes.** We will post changes here with the date, and tell members by email about material
changes `[DECIDE: notice period, e.g. 14 days]`.

**14. Law and disputes.** `[DECIDE: governing law and courts; EU/UK consumers keep the protection of
their own country's mandatory law.]`

---

## B. Privacy Policy: what the current page (`views/legal/privacy.php`) is missing

The current page is accurate on location, analytics and deletion. Add:

| Gap | What to say (facts from the code) |
|---|---|
| Who is the controller | Legal name and contact from A.1 |
| Messages | Private messages and trip chat are stored on our servers so they can be delivered, readable by the two people (or the trip members) and by a moderator only when a message is reported |
| Hosts & Locals | A host profile holds a city and neighbourhood, never an address; references are visible on profiles; stay requests hold dates, guest count and an introduction |
| Date of birth | Collected to enforce 16+/18+, never displayed |
| Processors | Render (hosting and database), Cloudflare (network/CDN), Resend (email delivery), Google (only if you choose Google sign-in). `[DECIDE: confirm the list; R2 storage if enabled]` |
| Retention | Backups kept 30 days (docs/BACKUP_RESTORE.md); moderation records kept `[DECIDE]`; deleted accounts per app/account_delete.php |
| Legal basis (EU/UK) | Contract (running your account), legitimate interests (safety, anti-spam, aggregate counts), consent where asked (marketing email) |
| Your rights | Access, correction, deletion, portability, objection; complaint to a supervisory authority `[DECIDE: EU/UK representative if required]` |
| Children | No accounts under 16 |
| Transfers | Data may be processed in the US by the processors above `[COUNSEL: transfer mechanism]` |

## C. What reopening hosting needs (from docs/HOSTS_LOCALS_20261031.md, updated)

1. Terms section 7 reviewed and published. 2. Privacy additions above published. 3. A named moderator
with a response-time commitment for safety reports (the queue now emails admins and mods on every
report, and moderators can suspend an account from the queue). 4. An incident runbook: what a
moderator does on a stay safety report, within what time. 5. Then flip `rmt_hosting_paused()` to
false in app/hosts_locals.php.
