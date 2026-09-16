-- Migration 097 - "hide this", per member.
--
-- A report asks a moderator to judge something; a block ends contact with a person. Neither fits
-- "I do not want to see this one post again", which is the most common reaction and the one that
-- should cost nobody anything. A row here removes one item from one member's feed and lists, and
-- changes nothing for anybody else, including its author, who is never told.
CREATE TABLE IF NOT EXISTS hidden_content (
    user_id     INTEGER NOT NULL,
    target_type TEXT    NOT NULL,
    target_id   INTEGER NOT NULL,
    created_at  TIMESTAMP NOT NULL,
    PRIMARY KEY (user_id, target_type, target_id)
);
