-- Lisbon: promote a demo-seeded destination to a real, migrated row.
--
-- WHY THIS EXISTS. `lisbon-portugal` was only ever defined in database/seed.php — the demo
-- seeder. That seeder is hard-blocked in production (rmt_seed_data() refuses when APP_ENV=production
-- or DATABASE_URL is set), so Lisbon has no guaranteed row outside a locally-seeded database.
-- It was caught by scripts/verify_pgsql.php: building a database from migrations alone produced
-- 77 destinations, and Lisbon — one of the twenty launch destinations — was not among them, which
-- means its risk report would have been silently skipped by publish_risk_content.php in production
-- ("no such destination row: SKIP").
--
-- IDEMPOTENT BY DESIGN. Production probably DOES have this row, because the site was first
-- deployed with SEED_DEMO=1 and the seeder ran on that boot. That cannot be confirmed without
-- connecting to the live database, so this migration is written to be correct either way:
-- ON CONFLICT (slug) DO NOTHING inserts it if it is absent and leaves the existing row completely
-- untouched if it is present. It never overwrites live content.
--
-- Seven other destinations are in the same position (banff-canada, hoi-an-vietnam, kyoto-japan,
-- marrakech-morocco, oaxaca-mexico, queenstown-nz, reykjavik-iceland). They are NOT in the launch
-- twenty and are deliberately left alone here rather than bundled into an unrelated migration —
-- each needs its own researched summary and licensed photo. Tracked as a follow-up.
--
-- The photo was resolved through the Commons API (imageinfo + extmetadata) and carries an
-- explicit CC licence with attribution, matching migration 045.

INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url,
                          hero_credit, hero_license, hero_source_url, category, airport_codes)
VALUES
  ('lisbon-portugal', 'Lisbon', 'Portugal', 'Lisboa', 38.7223, -9.1393,
   'The city tax doubled to 4 euros per person per night in September 2024 and has held there through 2026, capped at seven nights — and the hills are steeper than any listing admits.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/2/29/Interlaced_tram_tracks_in_Alfama%2C_Lisbon.jpg/1280px-Interlaced_tram_tracks_in_Alfama%2C_Lisbon.jpg',
   'Eddpayne', 'CC BY-SA 4.0',
   'https://commons.wikimedia.org/wiki/File:Interlaced_tram_tracks_in_Alfama,_Lisbon.jpg',
   'city', 'LIS')
ON CONFLICT (slug) DO NOTHING;
