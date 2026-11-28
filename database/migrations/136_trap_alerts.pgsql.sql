-- Migration 136 - "New trap reported" alerts (app/trap_alerts.php, 2026-11-27).
-- One row per member per published traveler story they were emailed about, so a story is never
-- mailed to the same member twice however often scripts/send_trap_alerts.php runs.
CREATE TABLE IF NOT EXISTS trap_alerts_sent (
    user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    story_id INTEGER NOT NULL,
    sent_at  TEXT NOT NULL,
    PRIMARY KEY (user_id, story_id)
);
