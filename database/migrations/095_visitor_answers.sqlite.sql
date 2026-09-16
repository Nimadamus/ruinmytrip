-- Migration 095 - one question, asked once. See the pgsql file for why this exists and why it is
-- not called feedback.
CREATE TABLE IF NOT EXISTS visitor_answers (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    question    TEXT NOT NULL,
    answer      TEXT NOT NULL,
    note        TEXT,
    created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS visitor_answers_question_idx ON visitor_answers (question, created_at DESC);
CREATE INDEX IF NOT EXISTS visitor_answers_user_idx     ON visitor_answers (user_id, question);
