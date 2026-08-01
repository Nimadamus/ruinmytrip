-- Four more real destinations (Berlin, Lima, Hong Kong, Nairobi), same pattern as
-- migrations 016/018/021/022/024: base row here, real fact-checked summary/photo overwritten by
-- scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('berlin-germany', 'Berlin', 'Germany', 'Berlin', 52.5200, 13.4050,
   'Cold War history and legendary nightlife in a city still cheaper than most European capitals, though not for long.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1f/Berlin_Cathedral_%28Berliner_Dom%29_-_2013.jpg/1280px-Berlin_Cathedral_%28Berliner_Dom%29_-_2013.jpg', 'culture'),
  ('lima-peru', 'Lima', 'Peru', 'Lima', -12.0464, -77.0428,
   'A world-class food capital on the Pacific, with a sharp safety divide between Miraflores/Barranco and the rest of the city.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6a/Malecon_de_Miraflores%2C_Lima%2C_Peru.jpg/1280px-Malecon_de_Miraflores%2C_Lima%2C_Peru.jpg', 'food'),
  ('hong-kong', 'Hong Kong', 'Hong Kong', 'Hong Kong', 22.3193, 114.1694,
   'Dense, vertical and hyper-efficient, priced well above mainland China with real political-climate context worth knowing before you go.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/66/Hong_Kong_Skyline_Restitch_-_Dec_2007.jpg/1280px-Hong_Kong_Skyline_Restitch_-_Dec_2007.jpg', 'city'),
  ('nairobi-kenya', 'Nairobi', 'Kenya', 'Nairobi', -1.2921, 36.8219,
   'The only national park inside a capital city, and the real safari gateway, with a safety picture that demands real neighborhood-level awareness.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/Nairobi_Skyline.jpg/1280px-Nairobi_Skyline.jpg', 'adventure');
