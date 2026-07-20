<?php
declare(strict_types=1);

/**
 * Build the app config. In production (Render) everything comes from environment
 * variables — no secret files on disk. Locally it falls back to app/config.php.
 *
 * Recognised env vars:
 *   APP_ENV, APP_URL, APP_NAME, SECURITY_SALT
 *   DATABASE_URL           (postgres://user:pass@host:port/db)  -> pgsql
 *   or DB_DRIVER=mysql + MYSQL_HOST/NAME/USER/PASS/PORT
 */
function rmt_load_config(): array {
    $env = getenv('APP_ENV') ?: null;
    $dbUrl = getenv('DATABASE_URL') ?: null;

    if ($env === 'production' || $dbUrl) {
        $cfg = [
            'app_env'   => 'production',
            'app_url'   => rtrim(getenv('APP_URL') ?: 'https://ruinmytrip.com', '/'),
            'app_name'  => getenv('APP_NAME') ?: 'RuinMyTrip',
            'session_name'  => 'rmt_sess',
            'security_salt' => getenv('SECURITY_SALT') ?: 'CHANGE_ME',
            'sqlite_path'   => dirname(__DIR__) . '/database/ruinmytrip.sqlite',
            'mysql'         => ['host'=>'','name'=>'','user'=>'','pass'=>'','port'=>3306],
        ];
        if ($dbUrl) {
            $p = parse_url($dbUrl);
            $cfg['db_driver'] = 'pgsql';
            $cfg['pgsql'] = [
                'host' => $p['host'] ?? 'localhost',
                'port' => $p['port'] ?? 5432,
                'name' => ltrim($p['path'] ?? '', '/'),
                'user' => urldecode($p['user'] ?? ''),
                'pass' => urldecode($p['pass'] ?? ''),
                // Render internal URL needs no SSL; external does. Honor an override.
                'sslmode' => getenv('DB_SSLMODE') ?: '',
            ];
        } else {
            $cfg['db_driver'] = 'mysql';
            $cfg['mysql'] = [
                'host' => getenv('MYSQL_HOST') ?: 'localhost',
                'name' => getenv('MYSQL_NAME') ?: '',
                'user' => getenv('MYSQL_USER') ?: '',
                'pass' => getenv('MYSQL_PASS') ?: '',
                'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
            ];
        }
        return $cfg;
    }

    // Local dev. RMT_SQLITE points the app at an alternate database file, which is how the
    // production-shaped preview DB (scripts/dev_preview_db.php) gets served without disturbing
    // the demo-seeded one.
    $cfg = require dirname(__DIR__) . '/app/config.php';
    if ($alt = getenv('RMT_SQLITE')) $cfg['sqlite_path'] = $alt;
    if ($url = getenv('RMT_APP_URL')) $cfg['app_url'] = rtrim($url, '/');
    return $cfg;
}
