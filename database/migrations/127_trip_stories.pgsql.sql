-- Migration 127 - "Did something go wrong here? Tell us" on the tax and warnings pages.
--
-- A trip story is feedback about no place, so it lives in the feedback queue (058) and inherits its
-- one rule: nothing submitted changes anything. A person reads it, checks it and only then may it
-- be published. The topic says which page it came from (for example "warnings:amsterdam-netherlands"
-- or "tax:amsterdam") so the queue can be read city by city.
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS topic TEXT;
