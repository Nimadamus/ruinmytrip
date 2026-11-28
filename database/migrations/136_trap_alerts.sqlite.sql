-- Migration 136 - "New trap reported" alerts. See the pgsql file.
CREATE TABLE IF NOT EXISTS trap_alerts_sent (
    user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    story_id INTEGER NOT NULL,
    sent_at  TEXT NOT NULL,
    PRIMARY KEY (user_id, story_id)
);
