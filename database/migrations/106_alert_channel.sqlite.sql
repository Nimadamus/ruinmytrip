-- Migration 106 - the channel a match alert came from, kept on the alert itself.
--
-- A match alert is confirmed from an email, very often in a mail app's own browser, which carries
-- none of the cookie or session that recorded where the person first arrived. Without this, every
-- traveler who signs up that way reads as direct. The four words are the same closed, sanitised
-- values contribution_events holds (migration 094), copied at the moment the alert is set.
ALTER TABLE match_alerts ADD COLUMN acq_source   TEXT;
ALTER TABLE match_alerts ADD COLUMN acq_medium   TEXT;
ALTER TABLE match_alerts ADD COLUMN acq_campaign TEXT;
ALTER TABLE match_alerts ADD COLUMN acq_content  TEXT;
