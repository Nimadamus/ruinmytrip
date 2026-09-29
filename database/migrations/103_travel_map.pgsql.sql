-- Migration 103 - the travel map: the countries a member says they have been to.
--
-- A self-asserted stamp at country level, like visits is at city level: no GPS, no dates, never a
-- rating. Country is the key the map draws with (an ISO 3166 numeric code as text, or 'xk' for
-- Kosovo), so the map, the profile and the share card all read one column.
CREATE TABLE IF NOT EXISTS user_countries (
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    country    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, country)
);
CREATE INDEX IF NOT EXISTS idx_user_countries_country ON user_countries (country);
