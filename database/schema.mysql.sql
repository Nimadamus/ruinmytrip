-- RuinMyTrip schema (MySQL / production). Import in cPanel -> phpMyAdmin.
SET NAMES utf8mb4;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(24) UNIQUE NOT NULL,
  email VARCHAR(190) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(16) NOT NULL DEFAULT 'user',
  birthdate DATE NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE profiles (
  user_id INT PRIMARY KEY,
  display_name VARCHAR(60), bio TEXT, home_city VARCHAR(120),
  avatar_url VARCHAR(255), cover_url VARCHAR(255),
  credibility_score INT NOT NULL DEFAULT 0, links_json TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE destinations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(160) UNIQUE NOT NULL,
  name VARCHAR(160) NOT NULL, country VARCHAR(80), region VARCHAR(120),
  lat DOUBLE NULL, lng DOUBLE NULL, summary TEXT, hero_url VARCHAR(255), category VARCHAR(60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE trips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, destination_id INT NULL,
  title VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL, body MEDIUMTEXT,
  cover_url VARCHAR(255), visited_on DATE NULL,
  verified TINYINT NOT NULL DEFAULT 0, status VARCHAR(16) NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE SET NULL,
  KEY idx_trips_dest (destination_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE trip_photos (
  id INT AUTO_INCREMENT PRIMARY KEY, trip_id INT NOT NULL,
  url VARCHAR(255) NOT NULL, caption VARCHAR(255), sort INT DEFAULT 0,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, destination_id INT NULL,
  subject_type VARCHAR(24) NOT NULL DEFAULT 'destination', subject_name VARCHAR(200),
  rating TINYINT NOT NULL, title VARCHAR(200), body MEDIUMTEXT, visited_on DATE NULL,
  verified TINYINT NOT NULL DEFAULT 0, status VARCHAR(16) NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE SET NULL,
  KEY idx_reviews_dest (destination_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE guides (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, destination_id INT NULL,
  slug VARCHAR(200) UNIQUE NOT NULL, title VARCHAR(200) NOT NULL, summary TEXT, body MEDIUMTEXT,
  cover_url VARCHAR(255), premium TINYINT NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'published', created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE SET NULL,
  KEY idx_guides_dest (destination_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE follows (
  follower_id INT NOT NULL, followee_id INT NOT NULL, created_at DATETIME NOT NULL,
  PRIMARY KEY (follower_id, followee_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  target_type VARCHAR(24) NOT NULL, target_id INT NOT NULL,
  body TEXT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'published', created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE likes (
  user_id INT NOT NULL, target_type VARCHAR(24) NOT NULL, target_id INT NOT NULL,
  PRIMARY KEY (user_id, target_type, target_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE saves (
  user_id INT NOT NULL, target_type VARCHAR(24) NOT NULL, target_id INT NOT NULL,
  PRIMARY KEY (user_id, target_type, target_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE meetups (
  id INT AUTO_INCREMENT PRIMARY KEY, host_id INT NOT NULL, destination_id INT NULL,
  title VARCHAR(200) NOT NULL, description TEXT, date_start DATETIME NULL, date_end DATETIME NULL,
  visibility VARCHAR(16) NOT NULL DEFAULT 'public', capacity INT DEFAULT 0,
  safety_ack TINYINT NOT NULL DEFAULT 0, status VARCHAR(16) NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL,
  FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE SET NULL,
  KEY idx_meetups_dest (destination_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE meetup_rsvps (
  meetup_id INT NOT NULL, user_id INT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'going',
  PRIMARY KEY (meetup_id, user_id),
  FOREIGN KEY (meetup_id) REFERENCES meetups(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE going (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, destination_id INT NOT NULL,
  date_from DATE NULL, date_to DATE NULL, visibility VARCHAR(16) NOT NULL DEFAULT 'public',
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, type VARCHAR(32) NOT NULL,
  actor_id INT NULL, target_type VARCHAR(24), target_id INT, read_at DATETIME NULL, created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_notif_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT NOT NULL,
  target_type VARCHAR(24) NOT NULL, target_id INT NOT NULL, reason VARCHAR(120) NOT NULL, details TEXT,
  status VARCHAR(16) NOT NULL DEFAULT 'open', resolved_by INT NULL, created_at DATETIME NOT NULL,
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE blocks (
  blocker_id INT NOT NULL, blocked_id INT NOT NULL,
  PRIMARY KEY (blocker_id, blocked_id),
  FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
