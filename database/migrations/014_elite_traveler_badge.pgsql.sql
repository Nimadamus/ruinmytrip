-- Definition only, same pattern as 007_founding_traveler_rule: no user holds this until
-- rmt_award_badges() evaluates rmt_qualifies_elite_traveler() for them.
INSERT INTO badges (slug, name, description, icon) VALUES
  ('elite-traveler','Elite Traveler','Ten or more published reviews across five or more destinations, with real useful/funny/cool votes from other travelers.','★')
ON CONFLICT (slug) DO NOTHING;
