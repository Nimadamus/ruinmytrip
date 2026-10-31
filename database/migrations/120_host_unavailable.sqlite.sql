-- Migration 120 - host availability. See the pgsql file.
ALTER TABLE host_profiles ADD COLUMN unavailable TEXT;
