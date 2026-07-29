-- Four more real destinations (New Orleans, Rome, Bangkok, Sydney), same pattern as migration
-- 016: base row here, real fact-checked summary/photo overwritten by scripts/publish_editorial.php
-- from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('new-orleans-usa', 'New Orleans', 'United States', 'Louisiana', 29.9511, -90.0715,
   'French and Spanish colonial architecture, live jazz on nearly every block, and real hurricane-season planning.',
   'https://upload.wikimedia.org/wikipedia/commons/3/37/Bourbon_St%2C_French_Quarter%2C_New_Orleans%2C_USA2.jpg', 'food'),
  ('rome-italy', 'Rome', 'Italy', 'Lazio', 41.9028, 12.4964,
   'Ancient and Renaissance sites at unmatched density, now heavily gated by timed entry and post-Jubilee crowds.',
   'https://upload.wikimedia.org/wikipedia/commons/7/7a/Colosseum_in_rome.jpg', 'culture'),
  ('bangkok-thailand', 'Bangkok', 'Thailand', 'Bangkok', 13.7563, 100.5018,
   'Gilded temples and legendary street food, alongside some of Southeast Asia''s worst dry-season air quality.',
   'https://upload.wikimedia.org/wikipedia/commons/b/bd/Wat_Arun_from_Chao_Phraya_River.jpg', 'culture'),
  ('sydney-australia', 'Sydney', 'Australia', 'New South Wales', -33.8688, 151.2093,
   'One of the world''s most photographed harbours, paired with a genuinely high cost of living.',
   'https://upload.wikimedia.org/wikipedia/commons/8/81/Sydney_Opera_House_and_Harbour_Bridge_Dusk_2019-06-21.jpg', 'adventure');
