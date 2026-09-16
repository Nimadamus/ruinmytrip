-- Migration 096 - the languages a traveler speaks.
--
-- Whether two strangers can actually talk is the first practical question about meeting, and the
-- profile had no way to answer it. Stored as a short comma separated list of codes from a closed
-- vocabulary in app/profiles.php, never free text, so it can be matched on and cannot carry anything
-- a person did not choose from a list. Optional, empty by default, shown only when filled in.
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS languages TEXT;
