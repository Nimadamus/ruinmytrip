-- Migration 053 - contribution milestones.
--
-- Portable across drivers: this is six rows in an existing table and nothing else.
--
-- Restraint is the design. Six badges, every one of them a statement about work somebody actually
-- did, and no badge for logging in, visiting, or being early to a page. A wall of trinkets makes a
-- contribution look cheap, and the people we want writing reviews are the ones who would find that
-- insulting. "10 reviews" means ten reviews.
--
-- Awarding is driven by counting real rows every time (see rmt_award_badges), never by a stored
-- counter that could drift and never by hand, so a review that is later removed cannot leave a
-- milestone standing on nothing.

INSERT INTO badges (slug, name, description, icon) VALUES
  ('first-review',      'First Review',       'Wrote their first traveler review.', '1'),
  ('reviewer-5',        '5 Reviews',          'Five published traveler reviews.', '5'),
  ('reviewer-10',       '10 Reviews',         'Ten published traveler reviews.', '10'),
  ('reviewer-25',       '25 Reviews',         'Twenty-five published traveler reviews.', '25'),
  ('photo-contributor', 'Photo Contributor',  'Added photographs to five reviews or trips.', 'P'),
  ('helpful-reviewer',  'Helpful Reviewer',   'Ten travelers marked their reviews useful.', 'H');
