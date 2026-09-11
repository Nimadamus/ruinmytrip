-- Indexes for the reads this product actually does now, added after measuring rather than guessing.
--
-- trip_activities(place_id): a place page asks "who has this planned", which without this is a
-- sequential scan of every plan on the site to build one panel.
CREATE INDEX IF NOT EXISTS idx_activities_place ON trip_activities(place_id);

-- The two questions the joinable rail and the meetups list ask: what is open, and what is on a day.
CREATE INDEX IF NOT EXISTS idx_activities_open_day ON trip_activities(join_mode, day);

-- "Who has recommended anything here", which is the city page's answer half.
CREATE INDEX IF NOT EXISTS idx_activities_dest_rec ON trip_activities(destination_id, recommend)
   ;

-- Membership is now read inside the visibility clause of EVERY list on the site, so the lookup it
-- does has to be an index hit rather than a scan.
CREATE INDEX IF NOT EXISTS idx_trip_members_lookup ON trip_members(user_id, trip_id, state);
