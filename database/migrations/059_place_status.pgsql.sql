-- Migration 059 - one vocabulary for what state a place is in.
--
-- places.status held 'active' and 'closed', and 'closed' had to mean both "shut this week for
-- refurbishment" and "gone for good". Those are different facts and a reader needs to be told which
-- one, so the second gets its own value and a third arrives for the first.
--
-- No column changes. status is already TEXT and the values are application vocabulary, so this
-- migration only rewrites the rows that used the old word. Rerunning it matches nothing and does
-- nothing, which is what makes it safe on a container restart.

UPDATE places SET status = 'permanently_closed' WHERE status = 'closed';
