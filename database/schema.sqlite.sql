-- RuinMyTrip schema (SQLite / local dev). MySQL mirror in schema.mysql.sql.
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  email TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  birthdate TEXT,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS profiles (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  display_name TEXT,
  bio TEXT,
  home_city TEXT,
  avatar_url TEXT,
  cover_url TEXT,
  credibility_score INTEGER NOT NULL DEFAULT 0,
  links_json TEXT
);
CREATE TABLE IF NOT EXISTS destinations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT UNIQUE NOT NULL,
  name TEXT NOT NULL,
  country TEXT,
  region TEXT,
  lat REAL, lng REAL,
  summary TEXT,
  hero_url TEXT,
  category TEXT
);
CREATE TABLE IF NOT EXISTS trips (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  title TEXT NOT NULL,
  slug TEXT NOT NULL,
  body TEXT,
  cover_url TEXT,
  visited_on TEXT,
  verified INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'published',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS trip_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  url TEXT NOT NULL, caption TEXT, sort INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  subject_type TEXT NOT NULL DEFAULT 'destination',
  subject_name TEXT,
  rating INTEGER NOT NULL,
  title TEXT, body TEXT,
  visited_on TEXT,
  verified INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'published',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS guides (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  slug TEXT UNIQUE NOT NULL,
  title TEXT NOT NULL, summary TEXT, body TEXT, cover_url TEXT,
  premium INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'published',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS follows (
  follower_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  followee_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  PRIMARY KEY (follower_id, followee_id)
);
CREATE TABLE IF NOT EXISTS comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  target_type TEXT NOT NULL, target_id INTEGER NOT NULL,
  body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'published', created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS likes (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  target_type TEXT NOT NULL, target_id INTEGER NOT NULL,
  PRIMARY KEY (user_id, target_type, target_id)
);
CREATE TABLE IF NOT EXISTS saves (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  target_type TEXT NOT NULL, target_id INTEGER NOT NULL,
  PRIMARY KEY (user_id, target_type, target_id)
);
CREATE TABLE IF NOT EXISTS meetups (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  host_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER REFERENCES destinations(id) ON DELETE SET NULL,
  title TEXT NOT NULL, description TEXT,
  date_start TEXT, date_end TEXT,
  visibility TEXT NOT NULL DEFAULT 'public',
  capacity INTEGER DEFAULT 0,
  safety_ack INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'published',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS meetup_rsvps (
  meetup_id INTEGER NOT NULL REFERENCES meetups(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'going',
  PRIMARY KEY (meetup_id, user_id)
);
CREATE TABLE IF NOT EXISTS going (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  destination_id INTEGER NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
  date_from TEXT, date_to TEXT,
  visibility TEXT NOT NULL DEFAULT 'public',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL, actor_id INTEGER,
  target_type TEXT, target_id INTEGER,
  read_at TEXT, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  reporter_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  target_type TEXT NOT NULL, target_id INTEGER NOT NULL,
  reason TEXT NOT NULL, details TEXT,
  status TEXT NOT NULL DEFAULT 'open',
  resolved_by INTEGER, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS blocks (
  blocker_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  blocked_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  PRIMARY KEY (blocker_id, blocked_id)
);
CREATE INDEX IF NOT EXISTS idx_trips_dest ON trips(destination_id);
CREATE INDEX IF NOT EXISTS idx_reviews_dest ON reviews(destination_id);
CREATE INDEX IF NOT EXISTS idx_guides_dest ON guides(destination_id);
CREATE INDEX IF NOT EXISTS idx_meetups_dest ON meetups(destination_id);
CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, read_at);
CREATE TABLE IF NOT EXISTS sessions (
  id TEXT PRIMARY KEY,
  data TEXT NOT NULL DEFAULT '',
  updated_at INTEGER NOT NULL
);
