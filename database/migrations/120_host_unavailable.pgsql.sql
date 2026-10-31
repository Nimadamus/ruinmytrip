-- Host availability: dates a host is not hosting, as a JSON list of [first, last] dates, inclusive.
-- Stay requests touching any of those nights are refused. Past ranges are dropped on save.
ALTER TABLE host_profiles ADD COLUMN IF NOT EXISTS unavailable TEXT;
