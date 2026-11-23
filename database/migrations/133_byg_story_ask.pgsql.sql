-- Migration 133 - after the trip (2026-11-23). A confirmed signup with a trip date is asked once,
-- a few days after the date, what went wrong. Their answer goes to the story queue (POST /story),
-- where an editor checks it before anything is published. story_asked stamps the one email.
ALTER TABLE byg_signups ADD COLUMN IF NOT EXISTS story_asked TEXT;
