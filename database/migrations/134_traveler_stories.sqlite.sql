-- Migration 134 - traveler stories. See the pgsql file.
CREATE TABLE IF NOT EXISTS traveler_stories (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    feedback_id  INTEGER UNIQUE REFERENCES feedback(id) ON DELETE SET NULL,
    topic        TEXT NOT NULL,
    body         TEXT NOT NULL,
    when_text    TEXT,
    published_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    published_at TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT 'live'
);
CREATE INDEX IF NOT EXISTS idx_traveler_stories_topic ON traveler_stories (topic, status);
