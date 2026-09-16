-- Migration 097 - "hide this", per member. See the pgsql file.
CREATE TABLE IF NOT EXISTS hidden_content (
    user_id     INTEGER NOT NULL,
    target_type TEXT    NOT NULL,
    target_id   INTEGER NOT NULL,
    created_at  TEXT    NOT NULL,
    PRIMARY KEY (user_id, target_type, target_id)
);
