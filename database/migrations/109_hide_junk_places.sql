-- Fourteen imported place records that are not real venues: a street number, a church yard
-- filed as a restaurant, a painting filed as a restaurant, a branch of a chain with no address
-- to say which one. Reviewed page by page on 2026-10-29 (rel_1029/noindex_review_104.csv), none
-- had a Google impression in sixteen months. Hidden, not deleted: the row stays, place_show sends
-- the old URL to its city with a 301, and setting status back to 'active' restores the page.
-- Only rows nobody has reviewed or photographed are touched.
UPDATE places SET status = 'hidden'
 WHERE status = 'active'
   AND slug IN ('14-16-bangkok', 'blas-infante-barcelona', 'church-green-london', 'denny-s-tokyo',
                'greenpoint-branch-ymca-new-york-city', 'kings-cross-amsterdam',
                'kitazawa-town-hall-tokyo', 'nu-stadium-miami', 'parque-do-bairro-do-soutelo-lisbon',
                'san-francisco-amsterdam', 'the-exchange-new-york-city', 'the-last-judgement-london',
                'zona-coberta-com-mesa-lisbon', 'ダンダダン-tokyo')
   AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.place_id = places.id)
   AND NOT EXISTS (SELECT 1 FROM place_photos ph WHERE ph.place_id = places.id);
