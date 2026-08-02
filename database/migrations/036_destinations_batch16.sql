-- Four more real destinations (Las Vegas, Porto, Kathmandu, Maldives), same pattern as
-- migrations 016/018/021/022/024/027-035: base row here, real fact-checked summary/photo
-- overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('las-vegas-usa', 'Las Vegas', 'United States', 'Nevada', 36.1699, -115.1398,
   'The Strip priced itself past its own customers, lost 3.1 million visitors in a year, and is now propped up by conventions rather than gamblers.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1f/Las_Vegas_Strip_panorama.jpg/1280px-Las_Vegas_Strip_panorama.jpg', 'city'),
  ('porto-portugal', 'Porto', 'Portugal', 'Norte', 41.1579, -8.6291,
   'A UNESCO-listed river city that is still genuinely cheap to eat in, and is now taxing and licensing its way through the same overtourism squeeze as Lisbon.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/Dom_Luis_I_bridge_from_Cais_da_Ribeira.jpg/1280px-Dom_Luis_I_bridge_from_Cais_da_Ribeira.jpg', 'city'),
  ('kathmandu-nepal', 'Kathmandu', 'Nepal', 'Bagmati', 27.7172, 85.3240,
   'The gateway to the Himalaya, and repeatedly ranked among the most polluted cities on earth in exactly the months most trekkers pass through it.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e0/Boudhanath_stupa%2C_Kathmandu_01.jpg/1280px-Boudhanath_stupa%2C_Kathmandu_01.jpg', 'city'),
  ('maldives', 'Maldives', 'Maldives', 'Indian Ocean', 3.2028, 73.2207,
   'The world''s lowest-lying country, sold one island at a time, where the taxes went up in 2025 and the dry-island rules catch people out.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8d/Komandoo_%28Lhaviyani_Atoll%29.jpg/1280px-Komandoo_%28Lhaviyani_Atoll%29.jpg', 'beach');
