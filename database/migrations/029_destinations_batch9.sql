-- Four more real destinations (London, Paris, Chiang Mai, Cartagena), same pattern as
-- migrations 016/018/021/022/024/027/028: base row here, real fact-checked summary/photo
-- overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('london-uk', 'London', 'United Kingdom', 'England', 51.5074, -0.1278,
   'Centuries of history and world-class museums, priced against a real cost-of-living squeeze and Tube fares that add up fast.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a5/Palace_of_Westminster_from_the_dome_on_Methodist_Central_Hall.jpg/1280px-Palace_of_Westminster_from_the_dome_on_Methodist_Central_Hall.jpg', 'culture'),
  ('paris-france', 'Paris', 'France', 'Ile-de-France', 48.8566, 2.3522,
   'The Louvre, the Eiffel Tower and cafe culture, undercut by timed-entry ticket chaos and well-documented tourist scams.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/8/85/Tour_Eiffel_Wikimedia_Commons.jpg/1280px-Tour_Eiffel_Wikimedia_Commons.jpg', 'culture'),
  ('chiang-mai-thailand', 'Chiang Mai', 'Thailand', 'Northern Thailand', 18.7883, 98.9853,
   'Temples, digital-nomad cafes and real mountain scenery, badly compromised for months each year by burning-season air pollution.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/1/16/Wat_Phra_That_Doi_Suthep.jpg/1280px-Wat_Phra_That_Doi_Suthep.jpg', 'culture'),
  ('cartagena-colombia', 'Cartagena', 'Colombia', 'Bolivar', 10.3910, -75.4794,
   'A stunning colonial walled city on the Caribbean, priced like a resort inside the walls with a real safety gap just outside them.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6c/Cartagena_de_Indias_Colombia.jpg/1280px-Cartagena_de_Indias_Colombia.jpg', 'beach');
