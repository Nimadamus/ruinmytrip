-- Migration 103 - the travel map. See the pgsql file for the reasoning.
CREATE TABLE IF NOT EXISTS user_countries (
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    country    TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, country)
);
CREATE INDEX IF NOT EXISTS idx_user_countries_country ON user_countries (country);
