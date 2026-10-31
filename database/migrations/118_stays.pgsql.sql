-- Stay With a Local: requests to stay with an approved host.
--
-- requested  the guest asked, with dates, a guest count and an introduction
-- accepted   the host said yes; private messages open between the two
-- declined   the host said no (the guest is told only that it did not work out)
-- confirmed  the guest confirmed after the yes; this is the stay
-- cancelled  either side called it off before it began
--
-- Nothing here holds an address. Where to meet is agreed in private messages after a yes.
-- A confirmed stay whose last night has passed is what allows the "hosted" and "stayed" references.
CREATE TABLE IF NOT EXISTS stay_requests (
  id SERIAL PRIMARY KEY,
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
ALTER TABLE member_references ADD COLUMN IF NOT EXISTS rating_hospitality INTEGER;
