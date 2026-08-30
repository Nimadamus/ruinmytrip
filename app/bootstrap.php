<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/loadconfig.php';
$config = rmt_load_config();
$GLOBALS['config'] = $config;
require BASE_PATH . '/app/errors.php';

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/csrf.php';
require BASE_PATH . '/app/idempotency.php';
require BASE_PATH . '/app/mail.php';
require BASE_PATH . '/app/tokens.php';
require BASE_PATH . '/app/ratelimit.php';
require BASE_PATH . '/app/auth.php';
require BASE_PATH . '/app/reviews.php';
require BASE_PATH . '/app/review_aspects.php';
require BASE_PATH . '/app/editorial.php';
require BASE_PATH . '/app/editorial_blog.php';
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/place_data.php';
require BASE_PATH . '/app/indexability.php';
require BASE_PATH . '/app/neighborhoods.php';
require BASE_PATH . '/app/sitemap.php';
require BASE_PATH . '/app/similar_places.php';
require BASE_PATH . '/app/editorial_links.php';
require BASE_PATH . '/app/destination_modules.php';
require BASE_PATH . '/app/contribution_events.php';
require BASE_PATH . '/app/moderation.php';
require BASE_PATH . '/app/feedback.php';
require BASE_PATH . '/app/admin_places.php';
require BASE_PATH . '/app/meetups.php';
require BASE_PATH . '/app/going.php';
require BASE_PATH . '/app/communities.php';
require BASE_PATH . '/app/matching.php';
require BASE_PATH . '/app/posts.php';
require BASE_PATH . '/app/digest.php';
require BASE_PATH . '/app/notify_email.php';
require BASE_PATH . '/app/visits.php';
require BASE_PATH . '/app/profiles.php';
require BASE_PATH . '/app/tags.php';
require BASE_PATH . '/app/mentions.php';
require BASE_PATH . '/app/messages.php';
require BASE_PATH . '/app/storage.php';
require BASE_PATH . '/app/search_suggest.php';
require BASE_PATH . '/app/seo.php';
require BASE_PATH . '/app/session.php';

// Auto-migrate + seed on local SQLite so the site runs out of the box (before any session read).
// CLI tools that build their own database define RMT_NO_AUTOSEED first: they must not have a
// demo-seeded DB conjured underneath them just because the default file was absent.
if (!defined('RMT_NO_AUTOSEED') && $config['db_driver'] === 'sqlite' && !file_exists($config['sqlite_path'])) {
    require BASE_PATH . '/app/migrator.php';
    require BASE_PATH . '/database/seed.php';
    rmt_migrate_and_seed(db());
}

// DB-backed sessions: logins survive container restarts/deploys and work across instances.
// CLI has no session and no cookies, and starting one there hits the sessions table before a
// maintenance script has had the chance to create it.
if (PHP_SAPI !== 'cli') {
    session_set_save_handler(new DbSessionHandler(), true);
    session_name($config['session_name']);
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ($config['app_env'] === 'production')]);
    session_start();
}
