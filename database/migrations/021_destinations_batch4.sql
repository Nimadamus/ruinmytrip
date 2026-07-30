-- Four more real destinations (Mexico City, Istanbul, Edinburgh, Medellín), same pattern as
-- migrations 016/018: base row here, real fact-checked summary/photo overwritten by
-- scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('mexico-city-mexico', 'Mexico City', 'Mexico', 'Ciudad de México', 19.4326, -99.1332,
   'One of the world''s great food and museum cities, at 2,240m altitude with real air-quality and earthquake planning.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/3/36/Palacio_de_Bellas_Artes_CDMX.jpg/1280px-Palacio_de_Bellas_Artes_CDMX.jpg', 'food'),
  ('istanbul-turkiye', 'Istanbul', 'Türkiye', 'Marmara', 41.0082, 28.9784,
   '2,500 years of layered history straddling two continents, now priced in a rapidly inflating lira with real taxi friction.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/2/22/Hagia_Sophia_Mars_2013.jpg/1280px-Hagia_Sophia_Mars_2013.jpg', 'culture'),
  ('edinburgh-scotland', 'Edinburgh', 'United Kingdom', 'Scotland', 55.9533, -3.1883,
   'A stunning, walkable capital with a castle and an extinct volcano, undercut by August surge pricing and a new visitor levy.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/0/07/Edinburgh_Castle_from_Princes_Street_Gardens.jpg/1280px-Edinburgh_Castle_from_Princes_Street_Gardens.jpg', 'culture'),
  ('medellin-colombia', 'Medellín', 'Colombia', 'Antioquia', 6.2442, -75.5812,
   'Eternal-spring climate and a genuinely transformed city, with a safety picture that still demands real precautions.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/3/36/Medell%C3%ADn_skyline02.jpg/1280px-Medell%C3%ADn_skyline02.jpg', 'city');
