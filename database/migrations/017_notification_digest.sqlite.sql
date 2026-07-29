-- Weekly notification digest email (scripts/send_digest.php). Nothing has ever emailed a user
-- about activity before now -- notifications only ever existed in-app, so real activity (new
-- followers, votes, compliments) was invisible unless someone happened to open /notifications.
ALTER TABLE profiles ADD COLUMN last_digest_at TEXT;
ALTER TABLE profiles ADD COLUMN digest_opt_out INTEGER NOT NULL DEFAULT 0;
