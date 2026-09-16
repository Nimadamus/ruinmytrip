-- Migration 095 - one question, asked once, at the moment it is worth asking.
--
-- The gap. When real travelers arrive we can see exactly what they did and nothing at all about
-- why. The most useful thing to know about an early visitor is what they came hoping to find, and
-- the only way to know is to ask.
--
-- Deliberately not a survey platform: one row per answer, a closed question from our own short list,
-- a closed answer from that question's list, and an optional note the person typed. Asked once per
-- person, skippable, and never a popup.
--
-- Named `visitor_answers` rather than anything with "feedback" in it, because `feedback` already
-- exists and means something else here: corrections to a place. Two tables with one word between
-- them is how a later query silently reads the wrong one.
--
-- The note lives HERE and never in contribution_events, which stays free of anything a person
-- wrote. Telemetry records that an answer happened; this table holds what it said.

CREATE TABLE IF NOT EXISTS visitor_answers (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT REFERENCES users(id) ON DELETE CASCADE,
    question    TEXT NOT NULL,
    answer      TEXT NOT NULL,
    note        TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS visitor_answers_question_idx ON visitor_answers (question, created_at DESC);
CREATE INDEX IF NOT EXISTS visitor_answers_user_idx     ON visitor_answers (user_id, question);
