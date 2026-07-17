<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/loadconfig.php';
$config = rmt_load_config();
$GLOBALS['config'] = $config;

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/csrf.php';
require BASE_PATH . '/app/auth.php';
require BASE_PATH . '/app/seo.php';
require BASE_PATH . '/app/session.php';

// Auto-migrate + seed on local SQLite so the site runs out of the box (before any session read).
if ($config['db_driver'] === 'sqlite' && !file_exists($config['sqlite_path'])) {
    require BASE_PATH . '/database/seed.php';
    rmt_migrate_and_seed(db());
}

// DB-backed sessions: logins survive container restarts/deploys and work across instances.
session_set_save_handler(new DbSessionHandler(), true);
session_name($config['session_name']);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ($config['app_env'] === 'production')]);
session_start();
