-- Migration 094 - where a visit came from, in four words we choose from a closed list.
--
-- The gap. The event table records which of OUR pages somebody was on (`source`), and nothing at
-- all about how they reached the site, so "did that Reddit post produce a signup" has been an
-- unanswerable question. Four thousand sessions and no way to tell a link from a search from a
-- crawler following its own nose.
--
-- What is stored is deliberately not a referrer. A referrer is a URL, and a URL carries a path, a
-- query and sometimes a person's own words; keeping one would undo the promise this table has made
-- since it was built. What is stored instead is ONE WORD from a list this code owns: reddit,
-- facebook, x, instagram, tiktok, youtube, search, referral, direct or other. The referring host
-- is read for the length of one comparison, mapped to that word, and thrown away, exactly as the
-- crawler check reads the user agent and never writes it.
--
-- `acq_medium`, `acq_campaign` and `acq_content` come from utm parameters, sanitised to lowercase
-- letters, digits, dash and underscore, and capped, so a campaign label can say which post it was
-- without becoming a place to smuggle anything.
--
-- FIRST TOUCH WINS. The channel is decided on the first arrival that names one and then held for
-- the visit, so a person who lands from Reddit, reads three pages and signs up on the fourth is
-- still a Reddit signup rather than a direct one.

ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS acq_source   TEXT;
ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS acq_medium   TEXT;
ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS acq_campaign TEXT;
ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS acq_content  TEXT;
CREATE INDEX IF NOT EXISTS idx_contribution_events_acq ON contribution_events (acq_source, created_at);
