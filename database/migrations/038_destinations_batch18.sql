-- Four more real destinations (Naples, Phuket, Punta Cana, Ho Chi Minh City), same pattern as
-- migrations 016/018/021/022/024/027-037: base row here, real fact-checked summary/photo
-- overwritten by scripts/publish_editorial.php from database/editorial/content.json.
INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url, category) VALUES
  ('naples-italy', 'Naples', 'Italy', 'Campania', 40.8518, 14.2681,
   'The best-value big city in Italy and the one where you are most likely to lose your phone, sitting next to a Pompeii that now sells a fixed 20,000 tickets a day.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6f/Naples_from_the_Castello_Sant_Elmo_with_Abbazia_San_Martino_the_port_and_the_Vesuv.jpg/1280px-Naples_from_the_Castello_Sant_Elmo_with_Abbazia_San_Martino_the_port_and_the_Vesuv.jpg', 'city'),
  ('phuket-thailand', 'Phuket', 'Thailand', 'Phuket Province', 7.8804, 98.3923,
   'A record 5.41 million arrivals that did not translate into record revenue, an island running short of tap water, and the most organised jet ski scam in Southeast Asia.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/b/bd/Patong_Beach_in_Phuket.jpg/1280px-Patong_Beach_in_Phuket.jpg', 'beach'),
  ('punta-cana-dominican-republic', 'Punta Cana', 'Dominican Republic', 'La Altagracia', 18.5601, -68.3725,
   'The Caribbean''s busiest airport, eleven million passengers a year, and a seaweed problem that set an all-time Atlantic record the year before you booked.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b8/2023_-_Playa_Bavaro_Punta_Cana_-_01.jpg/1280px-2023_-_Playa_Bavaro_Punta_Cana_-_01.jpg', 'beach'),
  ('ho-chi-minh-city-vietnam', 'Ho Chi Minh City', 'Vietnam', 'Southeast', 10.7769, 106.7009,
   'Nine million motorbikes, a brand new metro line carrying 20 million passengers a year, and a taxi trade that clones the logos of the companies you were told to trust.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fd/Ho_Chi_Minh_City_Skyline_2022_%281%29.jpg/1280px-Ho_Chi_Minh_City_Skyline_2022_%281%29.jpg', 'city');
