-- Four more real destinations (Stockholm, Osaka, Montego Bay, Boracay), same pattern as
-- migrations 016/018/021/022/024/027/028/029/030/031/032/033/034: base row here, real
-- fact-checked summary/photo overwritten by scripts/publish_editorial.php from
-- database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('stockholm-sweden', 'Stockholm', 'Sweden', 'Stockholm', 59.3293, 18.0686,
   'Fourteen islands of old-town charm and archipelago boat trips, priced at Scandinavian levels that catch first-time visitors off guard.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Stockholm-Gamla-Stan-panorama.jpg/1280px-Stockholm-Gamla-Stan-panorama.jpg', 'city'),
  ('osaka-japan', 'Osaka', 'Japan', 'Kansai', 34.6937, 135.5023,
   'Japan''s street-food and nightlife capital, more working-class and less formal than Kyoto, now riding the same weak-yen tourism surge.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e4/Osaka_Castle_02bs3200.jpg/1280px-Osaka_Castle_02bs3200.jpg', 'city'),
  ('montego-bay-jamaica', 'Montego Bay', 'Jamaica', 'Saint James', 18.4762, -77.8939,
   'All-inclusive resorts at resort-bubble prices, with a real and worth-knowing safety gap the moment you step outside the gates.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/99/Panorama_Montego_Bay.jpg/1280px-Panorama_Montego_Bay.jpg', 'beach'),
  ('boracay-philippines', 'Boracay', 'Philippines', 'Aklan', 11.9674, 121.9248,
   'White Beach after a full six-month environmental shutdown and rebuild, now capped and managed instead of left to overrun itself.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/1/14/Boracay_White_Beach_Station_2_sunset_2_%28Malay%2C_Aklan%3B_04-06-2024%29.jpg/1280px-Boracay_White_Beach_Station_2_sunset_2_%28Malay%2C_Aklan%3B_04-06-2024%29.jpg', 'beach');
