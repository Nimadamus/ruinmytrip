-- Four more real destinations (Singapore, Athens, Rio de Janeiro, Tel Aviv), same pattern as
-- migrations 016/018/021/022/024/027/028/029/030: base row here, real fact-checked summary/photo
-- overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('singapore', 'Singapore', 'Singapore', 'Singapore', 1.3521, 103.8198,
   'Ultra-clean and hyper-efficient, with strict laws that catch tourists off guard and a real price gap between hawker centers and everything else.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/1_singapore_city_skyline_dusk_panorama_2011.jpg/1280px-1_singapore_city_skyline_dusk_panorama_2011.jpg', 'city'),
  ('athens-greece', 'Athens', 'Greece', 'Attica', 37.9838, 23.7275,
   'The Acropolis and millennia of history, now rationed by timed-entry tickets and summer heatwave closures.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/7/70/2020_Acropolis_of_Athens.jpg/1280px-2020_Acropolis_of_Athens.jpg', 'culture'),
  ('rio-de-janeiro-brazil', 'Rio de Janeiro', 'Brazil', 'Rio de Janeiro', -22.9068, -43.1729,
   'Christ the Redeemer and Copacabana at their best, with a real, well-documented safety gap between the tourist beaches and everywhere else.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9e/Rio_de_Janeiro_-_Vista_do_Corcovado.jpg/1280px-Rio_de_Janeiro_-_Vista_do_Corcovado.jpg', 'beach'),
  ('tel-aviv-israel', 'Tel Aviv', 'Israel', 'Tel Aviv District', 32.0853, 34.7818,
   'Beach city energy and one of the world''s most expensive food scenes, with a security situation worth checking your own government''s current advisory on before booking.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/60/Tel_Aviv_Beach.jpg/1280px-Tel_Aviv_Beach.jpg', 'beach');
