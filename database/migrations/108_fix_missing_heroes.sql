-- Three destination heroes pointed at Wikimedia Commons files that no longer exist (checked
-- 2026-10-29 through the Commons API), so the city page, /explore and the blog showed a broken
-- image. Replaced with licensed photos of the same places, credited. Only rows still holding the
-- dead file are touched.
UPDATE destinations SET
  hero_url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2d/Amazon_Theatre%2C_Teatro_Amazonas._Manaus%2C_Brazil._01.jpg/1280px-Amazon_Theatre%2C_Teatro_Amazonas._Manaus%2C_Brazil._01.jpg',
  hero_credit = 'Ridiculopathy', hero_license = 'CC0',
  hero_source_url = 'https://commons.wikimedia.org/wiki/File:Amazon_Theatre,_Teatro_Amazonas._Manaus,_Brazil._01.jpg'
WHERE slug = 'manaus-brazil' AND hero_url LIKE '%Teatro_Amazonas_Manaus_Brazil.jpg%';

UPDATE destinations SET
  hero_url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e2/Buzios-Arma%C3%A7ao2.jpg/1280px-Buzios-Arma%C3%A7ao2.jpg',
  hero_credit = 'Fulviusbsas', hero_license = 'CC BY-SA 3.0',
  hero_source_url = 'https://commons.wikimedia.org/wiki/File:Buzios-Arma%C3%A7ao2.jpg'
WHERE slug = 'buzios-brazil' AND hero_url LIKE '%Buzios_beach.jpg%';

UPDATE destinations SET
  hero_url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/Azadi_Tower%2C_Tehran.jpg/1280px-Azadi_Tower%2C_Tehran.jpg',
  hero_credit = 'Bernard Gagnon', hero_license = 'CC BY-SA 4.0',
  hero_source_url = 'https://commons.wikimedia.org/wiki/File:Azadi_Tower,_Tehran.jpg'
WHERE slug = 'tehran-iran' AND hero_url LIKE '%Azadi_Tower_%28431569207%29.jpg%';
