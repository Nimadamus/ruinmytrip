-- Four more real destinations (Krakow, Nice, Nassau, Manila), same pattern as
-- migrations 016/018/021/022/024/027-038: base row here, real fact-checked summary/photo
-- overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('krakow-poland', 'Krakow', 'Poland', 'Lesser Poland', 50.0647, 19.9450,
   'Fifteen million visitors a year in a medieval old town the size of a few blocks, an hour from a memorial that 1.95 million people visited last year.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/0/08/Krak%C3%B3w_-_Sukiennice_%26_Wie%C5%BCa_Ratuszowa.jpg/1280px-Krak%C3%B3w_-_Sukiennice_%26_Wie%C5%BCa_Ratuszowa.jpg', 'culture'),
  ('nice-france', 'Nice', 'France', 'Provence-Alpes-Cote d''Azur', 43.7102, 7.2620,
   'The Riviera''s most accessible city, where the mayor tried twice to ban big cruise ships, lost twice, and got a regional cap instead.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/62/Nice_from_Castle_Hill_01.jpg/1280px-Nice_from_Castle_Hill_01.jpg', 'city'),
  ('nassau-bahamas', 'Nassau', 'Bahamas', 'New Providence', 25.0443, -77.3504,
   'The busiest cruise port in the Caribbean, where 86.5 per cent of the country''s record 12.5 million visitors never stay the night.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/5/53/Nassau_Cruise_Terminal%2C_Bahamas_%28March_14%2C_2024%29_01.jpg/1280px-Nassau_Cruise_Terminal%2C_Bahamas_%28March_14%2C_2024%29_01.jpg', 'beach'),
  ('manila-philippines', 'Manila', 'Philippines', 'Metro Manila', 14.5995, 120.9842,
   'An airport that moved a record 52 million people last year into a metro area ranked the 14th most congested on earth.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/Intramuros%2C_Ermita_skyline_drone_%28Manila%3B_09-06-2025%29.jpg/1280px-Intramuros%2C_Ermita_skyline_drone_%28Manila%3B_09-06-2025%29.jpg', 'city');
