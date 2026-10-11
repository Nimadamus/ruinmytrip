-- Migration 107 - a review written before an account exists, kept on the server.
--
-- Until now a guest review lived only in the PHP session between "Publish" and the account step.
-- Close the tab, let the session lapse, or open the confirmation from another browser and it was
-- gone. The row is keyed by sha256 of a random token held in an HttpOnly cookie, like every other
-- token here; it expires after 30 days and is deleted the moment the review is saved to an account.
CREATE TABLE IF NOT EXISTS review_drafts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash  TEXT NOT NULL UNIQUE,
    place_id    INTEGER,
    payload     TEXT NOT NULL,
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL,
    expires_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_review_drafts_expires ON review_drafts (expires_at);
