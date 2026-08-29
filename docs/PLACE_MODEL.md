# The place model

What a RuinMyTrip place is, what we are allowed to say about it, and where that information may
legitimately come from. Written alongside migration 047.

## 1. Identity

A place is the integer `places.id`. Nothing else.

- Reviews, saves, photos, hours and categories all point at the id.
- The slug is presentation. It is derived from the name and the destination and it may change.
- A rename goes through `rmt_place_rename()`, which updates name, name_key and slug in one step and
  records the retired slug in `place_slug_history`.
- `/p/{slug}` resolves the current slug first. On a miss it looks the slug up in history and issues
  a **301** to the place's *current* URL, read fresh from the row. Because the target is never read
  from history, a place renamed any number of times still costs exactly one hop and can never form
  a redirect chain.
- A place never becomes a new row because of a spelling change, a rebrand, or a reformatted
  address. Merging two genuinely different places is the failure this model exists to prevent, and
  it is the only one that is invisible to a reader.

## 2. What may be stored

Every attribute added in 047 is nullable and starts NULL. There is no backfill anywhere in the
migration.

| Column | Notes |
|---|---|
| `street_address`, `neighborhood`, `region`, `postal_code` | City and country are **not** stored: they belong to the destination the place already references. `region` falls back to the destination's. |
| `lat`, `lng` | Both or neither. `(0,0)` is rejected — it is what a failed geocode writes. |
| `phone` | Kept in the venue's own formatting, minus anything that is not part of a phone number. Never reformatted into a guessed E.164. |
| `website_url` | http/https only, host must contain a dot. A non-http scheme is rejected, never repaired. |
| `price_level` | 1–4. CHECK constraint on Postgres; enforced in PHP everywhere so SQLite agrees. |
| `category_id` | A subcategory from `place_categories`, and only one whose `bucket` matches `places.type`. |
| `timezone` | IANA name. Required before "open now" can be answered at all. |
| `data_source`, `data_source_url`, `data_checked_at` | Provenance. A factual claim on a place page has to be traceable, and a stale one needs a date to be re-checked against. |

## 3. Hours

`place_hours` is one row per interval, not seven text columns.

- Multiple intervals per day (lunch and dinner) are two rows.
- `closed = true` with NULL times is an explicit "shut on this day".
- **No row for a day means we do not know**, which is a different claim from "closed", and the page
  makes neither one up: unknown days are simply absent from the list.
- `closes < opens` is an interval running past midnight. It is stored that way, rendered that way,
  and emitted to schema.org that way.
- `valid_from` / `valid_through` exist so a holiday or seasonal exception is a row rather than a
  schema change. Nothing writes them yet.
- `rmt_place_open_now()` returns `null` — not `false` — when the day is unknown or the place has no
  timezone. "Closed" sends someone home; we only say it when the data supports it.

## 4. Photos

`place_photos` holds photos *of the place*. Review photos stay on the review. The gallery merges
both.

- Bytes are never duplicated. `storage_key` points at the existing `media` row, so a photo shown on
  both a review and a place page is one blob with two references.
- `review_photo_id` records that a row exists because of a review photo, so deleting the review
  removes the place reference with it and attribution stays correct.
- `is_cover` is enforced by a partial unique index: at most one cover per place, in the database.
- `alt_text` is a column, not an afterthought.
- The cover is the place's own picture or nothing. The destination hero is a photo of the city and
  is never substituted in — it would tell a share preview something false.

## 5. Presentation rule

The page renders what exists and nothing else. No "Hours: not available" box, no empty gallery
frame, no placeholder map. The whole "The basics" card is skipped when we hold none of its rows,
and each row is skipped on its own. Today every production place has NULL for all of it, so the
live pages are byte-for-byte what they were before 047 apart from the schema fix.

## 6. Where place data may come from

We do not invent business information and we do not take other platforms' review content. The
review corpus has to be ours; that is the whole moat. Options the codebase can support today,
roughly in order of how much I trust them:

1. **The venue's own published information.** Its website, its booking page, its posted opening
   hours. This is what `database/editorial/` and `scripts/verify_place_sources.py` already do for
   the editorial layer: a fact is written down with the URL it came from and the date it was
   checked. `data_source_url` + `data_checked_at` extend the same discipline to attributes.
   *Recommended as the default for the places we cover editorially.*
2. **Official tourism and government sources.** Ticket prices, tourist taxes, museum hours and
   opening seasons are frequently published by a city or a ministry. Already the backbone of the
   2026 cost pages.
3. **OpenStreetMap.** ODbL, explicitly redistributable with attribution, and it carries exactly the
   fields 047 added: address, coordinates, phone, website, opening_hours, cuisine. This is the only
   realistic route to coordinates at scale. Requires an attribution line wherever OSM data renders
   and a `data_source = 'osm'` marker on every row so it can be re-synced or removed wholesale.
   *Recommended as the bulk source for address and coordinates.*
4. **Wikidata / Wikipedia** for landmarks and museums: CC0 coordinates and identifiers, good
   coverage of exactly the attraction type we already write about.
5. **Business owner claims**, later. A verified owner updating their own hours and phone is the
   highest-quality source there is and it costs us nothing to store — the schema already supports
   it via `data_source = 'owner'`.
6. **Community edits with moderation**, later. Same pattern as any other user-generated object:
   `status`, reports, and an audit trail.

Explicitly ruled out: scraping Google, Yelp or Tripadvisor for place data or review text. Their
review corpora are licensed content and copying them would replace our only durable advantage with
someone else's.

## 7. Recorded, not yet done

- **Sitemap scaling.** `rmt_sitemap_entries()` runs ~15 unbounded table scans per request with no
  caching. Correct at 575 URLs; it needs a sitemap index with partitioned child sitemaps
  (places / destinations / content / profiles) and cached generation before any programmatic
  expansion. Not a bottleneck today.
- **Destination hierarchy.** `destinations.country` is free text with no countries table and no
  neighborhood tier. Place-level `neighborhood` is a text column for now; it becomes an entity when
  there is enough data to justify neighborhood pages.
- **Category landing pages.** `place_categories.slug` is globally unique specifically so
  `/hotels/{city}/{category}` style URLs are addressable later. Nothing generates them yet, and
  nothing should until a category in a city has enough real places and reviews behind it.
