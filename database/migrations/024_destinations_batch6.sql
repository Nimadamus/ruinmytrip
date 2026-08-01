-- Four more real destinations (Amsterdam, Vienna, Seoul, Zanzibar), same pattern as
-- migrations 016/018/021/022: base row here, real fact-checked summary/photo overwritten by
-- scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('amsterdam-netherlands', 'Amsterdam', 'Netherlands', 'North Holland', 52.3676, 4.9041,
   'Canals and cycling under an active anti-overtourism campaign, cruise-ship ban and hotel construction freeze.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/8/85/Amsterdam_Canal.jpg/1280px-Amsterdam_Canal.jpg', 'city'),
  ('vienna-austria', 'Vienna', 'Austria', 'Vienna', 48.2082, 16.3738,
   'Imperial palaces and coffeehouse culture, with tourist-priced classical concerts and a real booking-ahead requirement at the big sights.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Wien_-_Karlskirche_%281%29.JPG/1280px-Wien_-_Karlskirche_%281%29.JPG', 'culture'),
  ('seoul-south-korea', 'Seoul', 'South Korea', 'Seoul Capital Area', 37.5665, 126.9780,
   'Palaces, K-culture and world-class transit, with a K-ETA entry requirement and real hanbok-rental crowding at the big palaces.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/63/Gyeongbokgung_01.jpg/1280px-Gyeongbokgung_01.jpg', 'city'),
  ('zanzibar-tanzania', 'Zanzibar', 'Tanzania', 'Zanzibar', -6.1659, 39.2026,
   'Spice-island beaches and Stone Town history, priced against real entry fees, a malaria risk zone and a land-rights displacement controversy behind the resort boom.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/Zanzibar_Stone_Town.jpg/1280px-Zanzibar_Stone_Town.jpg', 'beach');
