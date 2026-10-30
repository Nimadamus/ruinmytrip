-- Two more English names, from each venue's Wikidata label (checked 2026-10-29): Q137415707 and
-- Q1190230. The other 40 native script names have no English name in OpenStreetMap or Wikidata
-- and keep their original name, which is accurate.
UPDATE places SET name = 'Cafe La Boheme' WHERE slug = 'カフェ-ラ-ボエム-tokyo' AND name = 'カフェ・ラ・ボエム';
UPDATE places SET name = 'Kawasaki Todoroki Stadium' WHERE slug = 'kawasaki-todoroki-stadium-tokyo' AND name = '等々力陸上競技場';
