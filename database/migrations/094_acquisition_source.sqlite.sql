-- sqlite mirror of 094_acquisition_source.pgsql.sql. See that file for why a referrer is read and
-- never written, and why the first arrival that names a channel is the one that counts.

ALTER TABLE contribution_events ADD COLUMN acq_source TEXT;
ALTER TABLE contribution_events ADD COLUMN acq_medium TEXT;
ALTER TABLE contribution_events ADD COLUMN acq_campaign TEXT;
ALTER TABLE contribution_events ADD COLUMN acq_content TEXT;
CREATE INDEX IF NOT EXISTS idx_contribution_events_acq ON contribution_events (acq_source, created_at);
