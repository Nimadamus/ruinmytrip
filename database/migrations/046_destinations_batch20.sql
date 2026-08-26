-- Manaus, Buzios, San Francisco, Tehran. Base rows; summaries/photos can be overwritten
-- by publish_editorial.php when a full editorial entry exists.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('manaus-brazil', 'Manaus', 'Brazil', 'Amazonas', -3.1190, -60.0217,
   'A gilded opera house in the Amazon, the Meeting of the Waters, and the real jungle a boat ride away, with yellow fever prep and app-only transport as the price of entry.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Teatro_Amazonas_Manaus_Brazil.jpg/1280px-Teatro_Amazonas_Manaus_Brazil.jpg', 'adventure'),
  ('buzios-brazil', 'Buzios', 'Brazil', 'Rio de Janeiro', -22.7469, -41.8817,
   'Twenty-three beaches on a peninsula three hours from Rio, still the easy Brazilian beach town Brigitte Bardot put on the map, reachable by bus from about 19 US dollars.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Buzios_beach.jpg/1280px-Buzios_beach.jpg', 'beach'),
  ('san-francisco-usa', 'San Francisco', 'United States', 'California', 37.7749, -122.4194,
   'Hills, fog, and a real comeback: 24.2 million visitors forecast for 2026, a 14 percent hotel tax, 9 dollar cable cars, and Alcatraz tickets that sell out weeks ahead.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/GoldenGateBridge-001.jpg/1280px-GoldenGateBridge-001.jpg', 'city'),
  ('tehran-iran', 'Tehran', 'Iran', 'Tehran Province', 35.6892, 51.3890,
   'A vast mountain-backed capital of bazaars, food, and 20th-century architecture that most Western governments currently advise against visiting at all.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/Azadi_Tower_%28431569207%29.jpg/1280px-Azadi_Tower_%28431569207%29.jpg', 'culture')
ON CONFLICT (slug) DO NOTHING;
