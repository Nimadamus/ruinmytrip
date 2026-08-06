-- Five US destinations required by the 2026 risk-report launch set (Los Angeles, Miami, Orlando,
-- Honolulu, San Francisco). Same pattern as migrations 016/018/021/022/024/027-039: base row
-- here, full risk report published separately from database/editorial/risk_content.json via
-- scripts/publish_risk_content.php.
--
-- Every hero image below was resolved through the Commons API before this file was written
-- (imageinfo + extmetadata), carries an explicit CC licence, and is attributed in the same row —
-- guessing a Commons hash path 404s, and files whose licence reads only "Attribution" with no
-- licence URL are not usable here.
--
-- airport_codes is populated because search resolves a destination by IATA code, and for these
-- five the "which airport did you actually book?" mistake is a real trip-ruining one (LAX vs
-- Burbank, MCO vs Sanford, SFO vs Oakland vs San Jose).

INSERT INTO destinations (slug, name, country, region, lat, lng, summary, hero_url,
                          hero_credit, hero_license, hero_source_url, category, airport_codes) VALUES
  ('los-angeles-usa', 'Los Angeles', 'United States', 'California', 34.0522, -118.2437,
   'A city where the distance between two things you want to see is the whole trip, and a 14 per cent hotel tax plus a resort fee lands on top of the rate you were quoted.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/6/63/Downtown_Los_Angeles_and_110_freeway.jpg/1280px-Downtown_Los_Angeles_and_110_freeway.jpg',
   'Camiloarenivar', 'CC BY-SA 4.0',
   'https://commons.wikimedia.org/wiki/File:Downtown_Los_Angeles_and_110_freeway.jpg',
   'city', 'LAX, BUR, LGB, SNA, ONT'),

  ('miami-usa', 'Miami', 'United States', 'Florida', 25.7617, -80.1918,
   'Resort fees around 25 dollars a night are normal and 40 to 60 is not unusual, on top of a room rate that can triple on a peak weekend.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d8/Miami_View_From_Brickell_%2880090633%29.jpeg/1280px-Miami_View_From_Brickell_%2880090633%29.jpeg',
   'Gabriel Kaplan', 'CC BY 3.0',
   'https://commons.wikimedia.org/wiki/File:Miami_View_From_Brickell_(80090633).jpeg',
   'beach', 'MIA, FLL, PBI'),

  ('orlando-usa', 'Orlando', 'United States', 'Florida', 28.5384, -81.3789,
   'The gate price is the small number: a single Epic Universe day runs 139 to 199 dollars before parking, and a multi-day park-to-park ticket starts around 520.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f2/Lake_Eola_and_Orlando_Skyline_seen_in_2024.jpg/1280px-Lake_Eola_and_Orlando_Skyline_seen_in_2024.jpg',
   'JER3L1337', 'CC BY 4.0',
   'https://commons.wikimedia.org/wiki/File:Lake_Eola_and_Orlando_Skyline_seen_in_2024.jpg',
   'family', 'MCO, SFB, TPA'),

  ('honolulu-usa', 'Honolulu', 'United States', 'Hawaii', 21.3069, -157.8583,
   'From January 2026 the state Green Fee added 0.75 per cent to the accommodations tax, taking the combined lodging tax on Oahu to roughly 18.7 per cent before any resort fee.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/4/41/Waikiki-waikiki-beach-aerial-photography_2.jpg/1280px-Waikiki-waikiki-beach-aerial-photography_2.jpg',
   'Mcmrose', 'CC BY-SA 4.0',
   'https://commons.wikimedia.org/wiki/File:Waikiki-waikiki-beach-aerial-photography_2.jpg',
   'beach', 'HNL'),

  ('san-francisco-usa', 'San Francisco', 'United States', 'California', 37.7749, -122.4194,
   'Car break-ins are well down from their 2023 peak, but a six-block stretch between Union Square and Civic Center still accounts for a wildly disproportionate share of visitor incidents.',
   'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e2/San_Francisco_skyline_from_Golden_Gate_Bridge%2C_09_2017.jpg/1280px-San_Francisco_skyline_from_Golden_Gate_Bridge%2C_09_2017.jpg',
   'Mariordo (Mario Roberto Duran Ortiz)', 'CC BY-SA 4.0',
   'https://commons.wikimedia.org/wiki/File:San_Francisco_skyline_from_Golden_Gate_Bridge,_09_2017.jpg',
   'city', 'SFO, OAK, SJC')
-- Defensive, and never an overwrite. None of these five slugs appears in any earlier migration or
-- in the demo seeder, so on the current production database this clause does nothing. It is here
-- so the migration stays correct against a database whose history we cannot fully inspect: if a
-- row with one of these slugs already exists, its content is left exactly as it is rather than
-- being replaced by this file.
ON CONFLICT (slug) DO NOTHING;
