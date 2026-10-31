-- Migration 118 - stays. See the pgsql file.
CREATE TABLE IF NOT EXISTS stay_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  guest_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  host_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  date_from TEXT NOT NULL,
  date_to TEXT NOT NULL,
  guests INTEGER NOT NULL DEFAULT 1,
  intro TEXT NOT NULL,
  state TEXT NOT NULL DEFAULT 'requested',
  cancelled_by INTEGER,
  created_at TEXT NOT NULL,
  decided_at TEXT,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_stay_requests_host ON stay_requests (host_id, state);
CREATE INDEX IF NOT EXISTS idx_stay_requests_guest ON stay_requests (guest_id, state);
ALTER TABLE member_references ADD COLUMN rating_hospitality INTEGER;
