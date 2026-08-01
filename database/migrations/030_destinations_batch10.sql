-- Four more real destinations (Havana, Munich, Cancun, Vancouver), same pattern as migrations
-- 016/018/021/022/024/027/028/029: base row here, real fact-checked summary/photo overwritten by
-- scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('havana-cuba', 'Havana', 'Cuba', 'Havana', 23.1136, -82.3666,
   'Faded colonial grandeur and classic cars, run on a US-sanctioned cash-only economy with real infrastructure shortages.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/95/Old_Havana_Cuba.jpg/1280px-Old_Havana_Cuba.jpg', 'culture'),
  ('munich-germany', 'Munich', 'Germany', 'Bavaria', 48.1351, 11.5820,
   'Bavarian beer-hall tradition and Oktoberfest at full commercial scale, priced above the German average even outside festival season.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/Marienplatz_M%C3%BCnchen-1.jpg/1280px-Marienplatz_M%C3%BCnchen-1.jpg', 'culture'),
  ('cancun-mexico', 'Cancun', 'Mexico', 'Quintana Roo', 21.1619, -86.8515,
   'Caribbean all-inclusive resorts, hit hard some years by sargassum seaweed and a persistent timeshare-tout industry.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/94/Cancun_Beach.jpg/1280px-Cancun_Beach.jpg', 'beach'),
  ('vancouver-canada', 'Vancouver', 'Canada', 'British Columbia', 49.2827, -123.1207,
   'Mountains meet ocean in one of the world''s most expensive cities to visit, with a real, visible homelessness crisis downtown.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6c/Vancouver_skyline.jpg/1280px-Vancouver_skyline.jpg', 'nature');
