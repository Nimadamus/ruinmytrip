-- What a traveler is actually interested in, as a short fixed list they pick from.
-- See the pgsql file for why it is a table and why the vocabulary lives in code.
CREATE TABLE IF NOT EXISTS profile_interests (
  user_id  INTEGER NOT NULL,
  interest TEXT NOT NULL,
  PRIMARY KEY (user_id, interest)
);
CREATE INDEX IF NOT EXISTS idx_profile_interests_interest ON profile_interests (interest);
