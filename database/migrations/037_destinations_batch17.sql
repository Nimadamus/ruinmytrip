-- Four more real destinations (Venice, Copenhagen, Tulum, Siem Reap), same pattern as
-- migrations 016/018/021/022/024/027-036: base row here, real fact-checked summary/photo
-- overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('venice-italy', 'Venice', 'Italy', 'Veneto', 45.4408, 12.3155,
   'A city of under 48,000 residents that charges day-trippers to walk in, and where the gap between a tourist-trap bill and a real one is one bridge wide.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/1/17/Panorama_of_Canal_Grande_and_Ponte_di_Rialto%2C_Venice_-_September_2017.jpg/1280px-Panorama_of_Canal_Grande_and_Ponte_di_Rialto%2C_Venice_-_September_2017.jpg', 'city'),
  ('copenhagen-denmark', 'Copenhagen', 'Denmark', 'Capital Region', 55.6761, 12.5683,
   'One of the most liveable cities in Europe and one of the most expensive, where a canal-side main costs nearly double the same plate two blocks inland.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/c/ce/Kopenhagen_%28DK%29%2C_Nyhavn_--_2017_--_1711.jpg/1280px-Kopenhagen_%28DK%29%2C_Nyhavn_--_2017_--_1711.jpg', 'city'),
  ('tulum-mexico', 'Tulum', 'Mexico', 'Quintana Roo', 20.2114, -87.4654,
   'The cautionary tale of the Caribbean: it priced itself past its own customers, put a fee on its free beaches, and watched occupancy collapse.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cb/Maya_ruins_at_Tulum_2023_-_beach.jpg/1280px-Maya_ruins_at_Tulum_2023_-_beach.jpg', 'beach'),
  ('siem-reap-cambodia', 'Siem Reap', 'Cambodia', 'Siem Reap Province', 13.3671, 103.8448,
   'Base camp for Angkor, with a $37 ticket, an airport 50km out of town, and sandstone that turns into a furnace by mid-morning in April.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Angkor_Wat_with_its_reflection_%28cropped%29.jpg/1280px-Angkor_Wat_with_its_reflection_%28cropped%29.jpg', 'culture');
