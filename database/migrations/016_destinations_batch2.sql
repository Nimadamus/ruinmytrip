-- Four new real destinations (Prague, Cape Town, Cusco, Ubud), added alongside their
-- fact-checked editorial content (database/editorial/content.json, published separately via
-- scripts/publish_editorial.php). publish_editorial.php deliberately refuses to create a
-- destination row itself (a typo'd slug must fail loudly, not silently create garbage), so the
-- base row has to exist first. summary/hero_url here are placeholders overwritten by the
-- publish script's real researched summary and imported, attributed Wikimedia photo.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('prague-czechia', 'Prague', 'Czechia', 'Prague', 50.0755, 14.4378,
   'Gothic and Baroque old town on the Vltava, now navigating an overtourism crackdown.',
   'https://upload.wikimedia.org/wikipedia/commons/4/4c/Prague_Castle_from_Charles_Bridge_panorama.JPG', 'city'),
  ('cape-town-south-africa', 'Cape Town', 'South Africa', 'Western Cape', -33.9249, 18.4241,
   'Table Mountain, Cape Peninsula beaches and the Winelands, with real safety nuance to know first.',
   'https://upload.wikimedia.org/wikipedia/commons/7/7d/Table_Mountain_from_Table_Bay.jpg', 'adventure'),
  ('cusco-peru', 'Cusco', 'Peru', 'Cusco', -13.5320, -71.9675,
   'Inca capital and Sacred Valley gateway to Machu Picchu, with a stricter 2026 ticket system.',
   'https://upload.wikimedia.org/wikipedia/commons/0/02/Machu_Picchu%2C_Per%C3%BA%2C_2015-07-30%2C_DD_60.JPG', 'culture'),
  ('ubud-indonesia', 'Ubud', 'Indonesia', 'Bali', -8.5069, 115.2625,
   'Bali''s cultural and wellness hub among rice terraces, now facing a mandatory tourism levy and heavy traffic.',
   'https://upload.wikimedia.org/wikipedia/commons/a/aa/Tegallalang_Rice_Terraces.jpg', 'culture');
