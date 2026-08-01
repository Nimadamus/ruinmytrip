-- Four more real destinations (Jaipur, Petra, Dubrovnik, San Jose), same pattern as migrations
-- 016/018/021/022/024/027/028/029/030/031/032/033: base row here, real fact-checked
-- summary/photo overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('jaipur-india', 'Jaipur', 'India', 'Rajasthan', 26.9124, 75.7873,
   'The Pink City''s forts and palaces, priced on a steep foreigner-versus-local ticket gap and worked hard by gem-shop and tour-guide scams.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/Hawa_Mahal_2011.jpg/1280px-Hawa_Mahal_2011.jpg', 'culture'),
  ('petra-jordan', 'Petra', 'Jordan', 'Ma''an', 30.3285, 35.4444,
   'A rock-cut ancient city that earns every bit of its reputation, behind one of the steepest single-site entrance fees in world travel.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6e/Al_Khazneh_02.jpg/1280px-Al_Khazneh_02.jpg', 'culture'),
  ('dubrovnik-croatia', 'Dubrovnik', 'Croatia', 'Dubrovnik-Neretva', 42.6507, 18.0944,
   'A walled Adriatic old town turned into a real-life Game of Thrones set, now capped on cruise-ship arrivals to manage the crowds it created.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/9/94/Dubrovnik_Old_Town.jpg/1280px-Dubrovnik_Old_Town.jpg', 'beach'),
  ('san-jose-costa-rica', 'San Jose', 'Costa Rica', 'San Jose', 9.9281, -84.0907,
   'A transit gateway city most travelers pass straight through on the way to the beaches and rainforest that are the actual draw.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6a/San_Jose_Costa_Rica.jpg/1280px-San_Jose_Costa_Rica.jpg', 'city');
