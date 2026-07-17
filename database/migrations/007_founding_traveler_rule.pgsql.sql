-- Correct the Founding Traveler description to match the rule actually enforced in code
-- (rmt_qualifies_founding_traveler): one of the first 100 accounts AND has published a review.
-- The original text promised a "verified-email account", which cannot currently be earned —
-- verification email cannot reach real users until a sending domain is verified. A badge must
-- describe what it actually means.
UPDATE badges
   SET name = 'Founding Traveler',
       description = 'One of the first 100 travelers to join RuinMyTrip and publish a review.'
 WHERE slug = 'founding-traveler';
