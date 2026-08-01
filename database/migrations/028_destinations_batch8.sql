-- Four more real destinations (Accra, Santorini, Dubai, New York City), same pattern as
-- migrations 016/018/021/022/024/027: base row here, real fact-checked summary/photo overwritten
-- by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('accra-ghana', 'Accra', 'Ghana', 'Greater Accra', 5.6037, -0.1870,
   'West Africa''s diaspora-tourism capital, anchored by the weight of Cape Coast and Elmina''s slave castles nearby.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6c/Accra_Ghana.jpg/1280px-Accra_Ghana.jpg', 'culture'),
  ('santorini-greece', 'Santorini', 'Greece', 'South Aegean', 36.3932, 25.4615,
   'The postcard caldera view, now rationed by a cruise-passenger cap and a real seismic-risk conversation.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6a/Santorini_-_Oia.jpg/1280px-Santorini_-_Oia.jpg', 'beach'),
  ('dubai-uae', 'Dubai', 'United Arab Emirates', 'Dubai', 25.2048, 55.2708,
   'Record-breaking towers and theme parks in punishing summer heat, with real legal and cultural lines tourists still cross by accident.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/7/70/Burj_Khalifa.jpg/1280px-Burj_Khalifa.jpg', 'city'),
  ('new-york-city-usa', 'New York City', 'United States', 'New York', 40.7128, -74.0060,
   'The five boroughs, now priced against congestion pricing, surge hotel rates and a real tipping-culture learning curve.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f0/NYC_Montage_2014.jpg/1280px-NYC_Montage_2014.jpg', 'city');
