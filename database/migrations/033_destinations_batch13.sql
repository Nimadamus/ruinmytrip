-- Four more real destinations (Budapest, Zurich, Seminyak/Bali, Warsaw), same pattern as
-- migrations 016/018/021/022/024/027/028/029/030/031/032: base row here, real fact-checked
-- summary/photo overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('budapest-hungary', 'Budapest', 'Hungary', 'Central Hungary', 47.4979, 19.0402,
   'Thermal baths and ruin bars on the Danube, undercut by a well-documented taxi and club overcharging scam targeting tourists at night.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8f/Hungarian_Parliament_Building_at_night.JPG/1280px-Hungarian_Parliament_Building_at_night.JPG', 'culture'),
  ('zurich-switzerland', 'Zurich', 'Switzerland', 'Zurich', 47.3769, 8.5417,
   'Lake swimming and Alpine efficiency in one of the world''s most expensive cities, where a hotel stay buys free public transit.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/2/29/Zurich_-_Lake_Zurich_and_Alps_-_2011.jpg/1280px-Zurich_-_Lake_Zurich_and_Alps_-_2011.jpg', 'city'),
  ('seminyak-bali-indonesia', 'Seminyak', 'Indonesia', 'Bali', -8.6905, 115.1622,
   'Bali''s beach-club coast, distinct from Ubud''s cultural interior, now taxed on entry and dealing with real scooter-accident and beach-trash realities.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7a/Seminyak_Beach%2C_Bali.jpg/1280px-Seminyak_Beach%2C_Bali.jpg', 'beach'),
  ('warsaw-poland', 'Warsaw', 'Poland', 'Masovia', 52.2297, 21.0122,
   'A fully rebuilt Old Town and blunt WWII history, priced as one of Europe''s most budget-friendly capitals.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/1/16/Warsaw_Old_Town_Market_Square.jpg/1280px-Warsaw_Old_Town_Market_Square.jpg', 'culture');
