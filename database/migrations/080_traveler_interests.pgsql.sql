-- What a traveler is actually interested in, as a short fixed list they pick from.
--
-- The site knows how somebody travels (profiles.travel_style, migration 074) and where they want to
-- go (saves), and those are the only two things matching can use. Neither answers the question two
-- strangers deciding whether to meet actually have, which is "are we going to want to do the same
-- thing on Tuesday night".
--
-- A table rather than a column so a person can pick several, and so matching can count the overlap
-- in SQL rather than parsing a string. The vocabulary is fixed in code (RMT_INTERESTS), never
-- free text: free text cannot be matched on, and a free-text field about a person is a place for
-- things this site should not be storing.
CREATE TABLE IF NOT EXISTS profile_interests (
  user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  interest TEXT NOT NULL,
  PRIMARY KEY (user_id, interest)
);
CREATE INDEX IF NOT EXISTS idx_profile_interests_interest ON profile_interests (interest);
