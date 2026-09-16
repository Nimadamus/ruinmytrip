-- Migration 093 - one bit that separates a browser from a fetcher.
--
-- The problem this exists for, measured rather than assumed. The dashboard read 4,117 arrivals in
-- a window where Search Console recorded zero clicks, 8,066 of 8,272 sessions lasted zero seconds,
-- and 118 of 118 browsers never came back. Almost all of it is automated, and it was being counted
-- as people, which makes every rate on the page a lie in the flattering direction.
--
-- The honest discriminator is not a user agent, which anybody can set, and not an address, which we
-- deliberately do not keep. It is whether the client GIVES A COOKIE BACK. We set `rmt_v` on the
-- first response of every visit; a browser returns it on the next request and a crawler does not.
-- So `cookied` records, per event, whether this request arrived carrying a token we had previously
-- issued.
--
-- What that does and does not prove:
--   * cookied = 1 means the client stored and returned state. Automation can do this, but almost
--     none of it bothers, so it is strong evidence of a browser.
--   * cookied = 0 on the FIRST event of a visit is meaningless: a real person's first page has no
--     cookie yet either. It is only evidence when a session produced several events and not one of
--     them carried a token, which is a shape no browser produces.
--
-- Nothing is deleted and nothing is rewritten. Every row that exists keeps its value of NULL,
-- meaning "recorded before this bit existed", and the reports say so rather than guessing.

ALTER TABLE contribution_events ADD COLUMN IF NOT EXISTS cookied SMALLINT;
CREATE INDEX IF NOT EXISTS idx_contribution_events_cookied ON contribution_events (cookied, created_at);
