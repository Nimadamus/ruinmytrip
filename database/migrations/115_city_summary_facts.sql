-- Two destination summary facts corrected 2026-10-30 (owner approved SEO pass).
-- San Francisco: San Francisco Travel's updated forecast, press release of 2026-08-26,
--   https://www.sftravel.com/media/press-release/san-francisco-travel-forecasts-third-straight-year-growth-2027
--   lists 2026 visitor volume at 24.4M (its earlier forecast was 24.2M).
-- Buzios: the bus fare could not be verified from the operator, so it is removed.
-- Each UPDATE is guarded on the old exact text, so an edited summary is never overwritten and a
-- rerun does nothing. database/editorial/cities_20261030.json carries the same text, so the boot
-- time editorial publish agrees with this.
UPDATE destinations SET summary = 'Hills, fog, and a real comeback: 24.4 million visitors forecast for 2026, a 14 percent hotel tax, 9 dollar cable cars, and Alcatraz tickets that sell out weeks ahead.'
 WHERE slug = 'san-francisco-usa' AND summary = 'Hills, fog, and a real comeback: 24.2 million visitors forecast for 2026, a 14 percent hotel tax, 9 dollar cable cars, and Alcatraz tickets that sell out weeks ahead.';
UPDATE destinations SET summary = 'Twenty-three beaches on a peninsula three hours from Rio, still the easy Brazilian beach town Brigitte Bardot put on the map, reachable by direct coach from Rio''s Novo Rio bus station.'
 WHERE slug = 'buzios-brazil' AND summary = 'Twenty-three beaches on a peninsula three hours from Rio, still the easy Brazilian beach town Brigitte Bardot put on the map, reachable by bus from about 19 US dollars.';
