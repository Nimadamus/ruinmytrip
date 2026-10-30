-- Nine notable places carried only their Japanese or Thai name, so an English page titled them
-- in a script most of its readers cannot search with ("明治神宮野球場, Tokyo 2026"). The slug already
-- held the English name; this makes the title match it. name_key and name_norm are left alone so
-- the importer still matches the source record. Only rows still holding a non-Latin name change.
UPDATE places SET name = 'Meiji Jingu Stadium' WHERE slug = 'meiji-jingu-stadium-tokyo' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Middle Eastern Culture Center in Japan' WHERE slug = 'middle-eastern-culture-center-in-japan-tokyo' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Nissay Theatre' WHERE slug = 'nissay-theatre-tokyo' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Oi Racecourse' WHERE slug = 'oi-racecourse-tokyo' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Old Shimbashi Station Railway History Exhibition Hall' WHERE slug = 'old-shimbashi-station-railway-history-exhibition-hall-tokyo' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Tokyo Takarazuka Theater' WHERE slug = 'tokyo-takarazuka-theater' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Khlong Lat Mayom Floating Market' WHERE slug = 'khlong-lat-mayom-floating-market-bangkok' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Madame Tussauds Bangkok' WHERE slug = 'madame-tussaud-s-bangkok' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET name = 'Suphachalasai Stadium' WHERE slug = 'suphachalasai-stadium-bangkok' AND name NOT LIKE '%a%' AND name NOT LIKE '%e%' AND name NOT LIKE '%o%';
UPDATE places SET street_address = 'Rama I Road' WHERE slug = 'suphachalasai-stadium-bangkok' AND street_address = 'ถนนพระรามที่ 1';
