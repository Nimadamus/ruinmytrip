-- Four more real destinations (Barcelona, Tokyo, Cairo, Buenos Aires), same pattern as
-- migrations 016/018/021: base row here, real fact-checked summary/photo overwritten by
-- scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('barcelona-spain', 'Barcelona', 'Spain', 'Catalonia', 41.3851, 2.1734,
   'Gaudi architecture and Mediterranean beaches under a tourist tax hike and a real overtourism backlash.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/Sagrada_Familia_01.jpg/1280px-Sagrada_Familia_01.jpg', 'culture'),
  ('tokyo-japan', 'Tokyo', 'Japan', 'Kanto', 35.6762, 139.6503,
   'The world''s biggest metro area runs on precision transit, now stretched by a weak yen and record crowds.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Skyscrapers_of_Shinjuku_2-chome_from_Tokyo_Metropolitan_Government_Building_2020-06-17.jpg/1280px-Skyscrapers_of_Shinjuku_2-chome_from_Tokyo_Metropolitan_Government_Building_2020-06-17.jpg', 'city'),
  ('cairo-egypt', 'Cairo', 'Egypt', 'Cairo Governorate', 30.0444, 31.2357,
   'The Pyramids and the new Grand Egyptian Museum, with real traffic, pollution and persistent tout pressure.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/All_Gizah_Pyramids.jpg/1280px-All_Gizah_Pyramids.jpg', 'culture'),
  ('buenos-aires-argentina', 'Buenos Aires', 'Argentina', 'Buenos Aires', -34.6037, -58.3816,
   'Tango, steak and grand European-style boulevards, priced against Argentina''s chronic inflation and a volatile exchange rate.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/60/BuenosAires-Panoramica-DSC00524.jpg/1280px-BuenosAires-Panoramica-DSC00524.jpg', 'city');
