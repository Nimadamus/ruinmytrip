-- Migration 134 - traveler stories on city pages (2026-11-23, app/traveler_stories.php).
-- A story from the feedback queue (kind trip_story) that an editor read, checked and published,
-- with the editor's text. status live | down; a taken down story stays as a record of the decision.
CREATE TABLE IF NOT EXISTS traveler_stories (
    id           SERIAL PRIMARY KEY,
    feedback_id  INTEGER UNIQUE REFERENCES feedback(id) ON DELETE SET NULL,
    topic        TEXT NOT NULL,
    body         TEXT NOT NULL,
    when_text    TEXT,
    published_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    published_at TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT 'live'
);
CREATE INDEX IF NOT EXISTS idx_traveler_stories_topic ON traveler_stories (topic, status);
