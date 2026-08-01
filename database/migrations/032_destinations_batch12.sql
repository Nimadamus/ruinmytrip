-- Four more real destinations (Shanghai, Milan, Dublin, Casablanca), same pattern as migrations
-- 016/018/021/022/024/027/028/029/030/031: base row here, real fact-checked summary/photo
-- overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('shanghai-china', 'Shanghai', 'China', 'Shanghai', 31.2304, 121.4737,
   'A futuristic skyline over a real cash-and-app economy, with no Google, WhatsApp or Instagram behind the Great Firewall.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6e/Shanghai_skyline_from_the_Bund.jpg/1280px-Shanghai_skyline_from_the_Bund.jpg', 'city'),
  ('milan-italy', 'Milan', 'Italy', 'Lombardy', 45.4642, 9.1900,
   'Fashion, design and a genuinely stunning Duomo, priced up hard whenever fashion week or a trade fair is in town.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/1/12/Milano_Duomo_from_roof.jpg/1280px-Milano_Duomo_from_roof.jpg', 'culture'),
  ('dublin-ireland', 'Dublin', 'Ireland', 'Leinster', 53.3498, -6.2603,
   'Literary pubs and Guinness at the source, undercut by one of Europe''s worst hotel shortages and prices to match.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0e/Ha%27penny_Bridge_Dublin_Ireland.jpg/1280px-Ha%27penny_Bridge_Dublin_Ireland.jpg', 'city'),
  ('casablanca-morocco', 'Casablanca', 'Morocco', 'Casablanca-Settat', 33.5731, -7.5898,
   'Morocco''s business capital and the Hassan II Mosque, usually rushed through on the way to Marrakech rather than a destination in its own right.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6c/Hassan_II_Mosque_Grand_Mosque_Casablanca.jpg/1280px-Hassan_II_Mosque_Grand_Mosque_Casablanca.jpg', 'culture');
