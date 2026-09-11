-- Somebody who turned up to a stranger's plan has an answer worth as much as the host's.
--
-- Until now "how was it" was asked of the plan's owner only, and the answer was written onto the
-- plan row. A traveler who joined, went, and had an opinion had nowhere to put it, which is the
-- whole "turning up once and nothing follows it" problem: the site collected the strongest signal
-- it has and then dropped it.
--
-- Kept on the join rather than the plan, because the plan belongs to its owner. One row per person
-- per plan already exists, and this is that person's own answer, which nobody else overwrites.
ALTER TABLE activity_joins ADD COLUMN recommend INTEGER;
ALTER TABLE activity_joins ADD COLUMN answered_at TEXT;
