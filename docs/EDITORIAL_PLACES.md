# Editorial place pages: the repeatable process

This is how an attraction page gets from "we should cover the Louvre" to a live, fact-checked URL.
Follow it and a batch costs minutes per place instead of a quarter of an hour. Deviate from it and
the failure mode is not a broken build, it is a page that quietly states a wrong price.

The whole system rests on one rule: **every number on a place page is tied to a string that a script
can still find on an official page.** Not a note saying somebody checked. A re-runnable assertion.

## Before anything: the local database

```bash
export RMT_SQLITE="$(php -c php.local.ini scripts/dev_db.php --quiet)"
```

That provisions `database/dev.sqlite` (gitignored) if needed, migrates it, and health-checks it.
Run it at the start of a session and any time a command fails with something like
`no such table: users`.

**Never put anything you depend on in the session scratchpad under `%LOCALAPPDATA%\Temp`.** All
pipeline state now lives inside the repo and gitignored: `database/dev.sqlite`, the source cache in
`.cache/place_sources/`, and batch working files in `.work/`. `dev_db.php` refuses outright to
provision a database under any temp root, so the original failure cannot recur even by accident.

**Never put the dev database in the session scratchpad under `%LOCALAPPDATA%\Temp`.** That
directory is transient and is entitled to be cleared underneath you. On 2026-08-12 it was, mid
session, taking every working file with it and leaving a zero-byte stub where the database had
been. The first symptom was `SQLSTATE[HY000]: General error: 1 no such table: users`, which looks
like schema corruption and is nothing of the sort. `scripts/dev_db.php` exists so that failure is
detected immediately and in plain language rather than several commands later.

It detects and recovers from all of: missing, zero bytes, truncated, all-NUL bytes (the separate
C: corruption hazard, which keeps a plausible file size and only shows in the header), a valid
SQLite file with no tables, and a database missing core tables. A file it rejects is renamed to
`dev.sqlite.broken-<timestamp>` rather than deleted, so a real corruption event can still be
examined afterwards.

It refuses to run when `DATABASE_URL` is set or `APP_ENV=production`, and asserts the resolved
driver is sqlite before migrating, so it cannot touch production.

## Starting the local server

```bash
scripts/dev_server.sh          # default port 8099
```

Do not start the server by hand. On 2026-08-12 a server left running from an earlier batch was
still bound to the port, a second one bound alongside it, and requests were answered by whichever
won. A full page sweep then reported six brand new pages as 404 while older pages returned 200,
because the answering process was reading a database from two batches earlier.

That is the dangerous failure: not a crash, a plausible wrong answer that gets reported as
evidence. The script kills anything on the port, provisions the database, starts exactly one
server, and then asserts the served sitemap lists the same number of editorial places the database
holds. If a stale process is answering, it fails loudly instead of letting you check the wrong
build.

## The five steps

### 1. Pick candidates and probe the sources FIRST

**Do not guess ticket-page URLs. Give the script the site root and let it read the navigation.**

```bash
python scripts/probe_place_sources.py --discover --file roots.txt
```

Batch 7 began with 34 hand-guessed URLs like `example.org/en/visit`, and 19 of them were plain
404s. Nothing was blocked and nothing was missing: official sites publish their prices at
`/en/plan-your-visit`, `/besuch/preise`, `/bezoek/tickets`, `/en/article/visit-us,4993.html`, and a
dozen other shapes nobody would guess. Re-running the same candidates in `--discover` mode, which
follows each site's own navigation to its visitor-information pages, took the yield from **3 usable
sources out of 34 to 65 out of 94**.

`--discover` stays on the institution's own domain, so it can never wander onto a reseller, and it
skips shop, membership, donation and schools pages, which match the same words and never carry an
admission price. Use `--discover-limit` to follow more or fewer pages per root (default 6).

Once you know the real URLs, probe them directly:

```bash
python scripts/probe_place_sources.py --file candidates.txt
```

For each URL this reports whether a plain fetch works at all, and pulls out candidate assertion
strings (money, opening hours, free-admission phrases) with surrounding context.

**Do this before writing a word.** Roughly two thirds of official attraction sites refuse a plain
fetch or publish nothing assertable, and discovering that after writing a page is how time gets
wasted. If a source is unusable, the attraction does not get a page. That is the correct outcome:
citing a ticket reseller for a price defeats the entire point.

Known-good so far: toureiffel.paris, louvre.fr, sainte-chapelle.fr, nhm.ac.uk, tate.org.uk,
westminster-abbey.org, stpauls.co.uk, royalcollection.org.uk, rijksmuseum.nl, vangoghmuseum.nl,
stedelijk.nl, annefrank.org, sagradafamilia.org, shokoku-ji.jp, esbnyc.com, oneworldobservatory.com,
whitney.org, belvedere.at, tcd.ie, nms.ac.uk.

Known-blocked: metmuseum.org, moma.org, britishmuseum.org, musee-orsay.fr, hrp.org.uk,
nationalgallery.org.uk, museodelprado.es, amnh.org, chateauversailles.fr. Colosseum and Vatican
Museums fetch but publish no adult price.

**royalcollection.org.uk was NOT blocked, and this is the most useful lesson in this document.**
Every path on it and on its `rct.uk` alias returned 403, it had been known-good in batches 2 and 4,
and two live pages were an hour away from being retired over it. What the site actually does is
serve a page and refuse the next request seconds later, indefinitely. The fixed 1.5-second per-host
gap could not satisfy that, and in a full sweep the sibling page citing the same host spent the
allowance first, so *the batch, not the source*, decided whether a fact could be checked. Per-host
intervals are now adaptive and a host that has signalled gets more retries. Both pages verify on a
cold sweep.

**Before concluding a host is blocked, check whether it is merely slow.** A source that fails in a
full sweep and passes when checked alone is a pacing problem every time.

**A price the probe cannot see is not a price that is missing.** The money pattern originally knew
only `€ £ $ ¥`, so every attraction priced in zloty, koruna, forint, krona, krone or franc came back
"FETCHES, NOTHING TO ASSERT" and was written off as unsourceable. It was not. That single gap had
been silently capping coverage at western Europe for four batches. If a page fetches and looks
empty, check what currency it prices in before concluding anything.

### 2. Write the entry in `database/editorial/places.json`

**Never in `content.json`.** `build_editorial_content.py --merge` rebuilds any destination a later
research batch covers, so place content living in that file gets silently clobbered by the next
batch of destinations.

Required per place: `destination_slug`, `name`, `type`, `headline`, `body`, `what_great`,
`what_ruined`, three ratings, `meta_description`, `facts_checked`, plus the section columns.

The validator enforces the quality floor, so these are not suggestions:

- at least **9 of 13** sections filled, and `what_it_is`, `why_go`, `the_good`, `the_downsides`
  and `verdict` are always required
- `meta_description` between **70 and 165** characters, hand-written, never a truncated body slice
- every `facts_checked` entry needs `fact`, `url` **and `assert_text`**
- at least 2 checked facts
- no em dashes anywhere, no `visited_on` (editorial never claims a visit)

Leave a section out rather than padding it. A missing section renders as nothing; a padded one
renders as filler, and filler is what makes a page look autogenerated.

### 3. Verify the sources

```bash
python scripts/verify_place_sources.py                    # everything
python scripts/verify_place_sources.py --slug paris-france  # one destination
```

Exit code is non-zero if anything did not pass. The three failure states mean different things and
need opposite responses, which is why the tool separates them:

- **FAIL** the page loaded and no longer contains the asserted string. Either the copy is now wrong
  or the assertion was too brittle. Fix one or the other. **Never publish over a FAIL.**
- **BLOCKED** the site answered 200 with almost no content, which is an interstitial or soft block,
  not a page. Nothing is known about the fact either way. `www.stedelijk.nl` does exactly this after
  a handful of requests: it returns a 157-character stub rather than a 403.
- **UNREACHABLE** the fetch failed outright, after one retry.

`--allow-blocked` lets a publish proceed past BLOCKED sources, and should only be used when a human
has opened the page and confirmed the copy. It still prints them every run and labels them
UNCHECKED rather than verified, because a source that cannot be re-checked is the one most likely to
go stale without anyone noticing. Prefer dropping the page over relying on this.

Two encoding facts this script already handles, which cost real time to discover:

- Many official sites serve **windows-1252**, where the euro sign is byte 0x80. Decoding that as
  latin-1 turns it into a control character, and every price assertion fails for a reason that has
  nothing to do with the price. Decode order is header charset, document charset, utf-8, cp1252.
- Some official ticketing platforms will not serve a page until a **session cookie** exists. Greece's
  Hellenic Heritage store 307s every venue page to `/api/auth/ensure-token`; without a cookie jar the
  redirect never resolves and the source looks permanently unreachable. The fetcher keeps a jar, which
  is what makes `tickets.hh.gr` verifiable at all.
- Some sites serve a page and then **403 the next request seconds later**. That is rate limiting, so
  fetches are retried once after a pause. A source that fails twice is genuinely unusable.
- **308 Permanent Redirect** is not handled by Python 3.10's redirect handler, so a site that simply
  moved its page surfaced as UNUSABLE. The fetcher now follows it. Two official sources were written
  off for this reason before it was fixed.
- A TLS **"unable to get local issuer certificate"** failure is usually the platform trust store, not
  a bad site. The fetcher retries once against certifi's roots. Certificates are still fully
  validated; this is a different root list, not a bypass.

Choosing a good `assert_text`: prefer a string that pins the number to its label, like
`Adults: € 25. Visitors under 18: free.` over a bare `€25`. Matching is case-insensitive with
whitespace collapsed and tags stripped, so copy the phrase as the probe printed it.

### 4. Publish

```bash
php scripts/publish_editorial.php --check     # validate, touch nothing
php scripts/publish_editorial.php             # dry run against the configured DB
php scripts/publish_editorial.php --apply     # write
```

Against production, from the repo root, with the firewall opened to your /32 first:

```bash
DATABASE_URL='postgresql://...@dpg-....oregon-postgres.render.com:5432/...?sslmode=require' \
  php -c php.local.ini -d extension=pdo_pgsql scripts/publish_editorial.php --apply
```

`--apply` is one transaction and is idempotent: reruns update the same rows, matched on
(author, place_id), rather than stacking duplicates. Always confirm `COMMITTED.` before re-locking
the firewall. Resolve the database id at run time with `scripts/ci/resolve_render_db.sh`; never
trust a hardcoded `dpg-` id, because instances get replaced.

### 5. Smoke-test production

Every new `/p/{slug}` and `/d/{slug}/places` should return 200, the sitemap should grow by the right
number of URLs with no duplicates, and `scripts/smoke_test.sh https://ruinmytrip.com` should pass.

## The honesty invariants, and why they are not negotiable

These are enforced in code and covered by `tests/places_test.php`. They are the difference between a
review site and an SEO farm.

- **Editorial never becomes a rating.** `rmt_place_stats()` excludes the editorial account by role,
  so an Official Review never moves a place's community average, and `aggregateRating` is omitted
  from the markup entirely when no traveler has reviewed the place. We never assert a consensus we
  do not have.
- **Editorial is always labelled.** Authorship is the label: editorial means `users.role =
  'editorial'`, and every render path asks the same module, so there is no way to publish editorial
  that renders unlabelled.
- **No claimed visits.** Editorial carries no `visited_on` and no verified badge. Nobody from the
  team necessarily went, and the disclosure on every page says so.
- **Editorial place reviews stay off the destination review list.** A destination page leads with
  exactly one Official Review; the place ones are read on their own pages. Community reviews of
  places are NOT excluded, because a traveler's review of a hotel in a city belongs on that city's
  page.
- **`editorial_count` is tracked separately** from `review_count` in listings and never folded into
  it, so a place with an Official Review and no traveler reviews reads honestly instead of either
  claiming a review count or looking empty.
- **Internal links only point at pages with editorial behind them**, so cross-linking can never
  become a ring of empty doorway pages.

## When a source stops answering

Work down this list before concluding an attraction cannot be sourced. Every step below has, at
least once, turned a "permanently unsourceable" verdict into a live page.

1. **Is it slow rather than blocked?** Check the place on its own. If it passes alone and fails in a
   sweep, it is pacing. The fetcher backs off per host automatically now, but a host that needs more
   than 20 seconds between requests will still need thought.
2. **Is the URL simply wrong?** Run `--discover` on the site root. Roughly two thirds of "dead"
   sources in batch 7 were guessed paths that had never existed.
3. **Is the navigation JavaScript-rendered?** Discovery falls back to the site's own sitemap, which
   is static XML. This is what reached museonazionaleromano.it.
4. **Is the price in a currency the probe can see?** It knows about thirty now, in both orders, but
   check before believing "FETCHES, NOTHING TO ASSERT".
5. **Is it a certificate or redirect problem?** Both are handled, but the error text is worth
   reading rather than skimming.
6. **Only then**, consider `database/blocked_sources.json`. A host listed there reports UNCHECKED
   rather than failing, so one dead source cannot gate an unrelated publish. It ships empty on
   purpose. Adding a host to it is a decision to keep pages whose facts can no longer be re-checked,
   and it must never be used to get a *new* page published: a source that never verified is not a
   blocked source, it is an unsourced claim.

Nothing in that registry reaches a reader. No page renders source status, and none should. A source
we cannot re-fetch is not evidence that a price is wrong, and a warning on the page would tell a
traveler to distrust copy that nothing has contradicted.

## Knowing what is actually covered

```bash
python scripts/verify_place_sources.py --no-cache --json .work/verify.json
python scripts/coverage_report.py --verify .work/verify.json
```

Prints attraction and destination counts, how many places have all thirteen sections rather than the
nine the validator demands, how many have a price assertion passing *right now*, and every place
that is missing a section or has an assertion that did not pass. Run it at the end of a batch; the
counts are what decide the next one.

## Scaling notes

The per-place cost is now dominated by writing, not by verification. To go faster, batch step 1
across many candidates at once (the probe runs concurrently), then write only the ones that came
back usable. Expect roughly one in three candidate sources to be usable; pick candidates
accordingly.

Do not raise throughput by lowering the section floor or by accepting secondary sources for prices.
The floor is what stops this becoming the thing it is competing against.
