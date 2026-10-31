-- Email notification preferences: the categories a member has switched off, comma separated.
-- Empty means everything is on. profiles.digest_opt_out still switches every email off at once.
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS email_muted TEXT;
