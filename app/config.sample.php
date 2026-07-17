<?php
// Copy to app/config.php and fill in. config.php is gitignored.
return [
    'app_env'   => 'production',            // 'local' | 'production'
    'app_url'   => 'https://ruinmytrip.com',
    'app_name'  => 'RuinMyTrip',
    'db_driver' => 'mysql',                 // 'mysql' (prod) | 'sqlite' (local)
    'mysql'     => [
        'host' => 'localhost',
        'name' => 'DBNAME',
        'user' => 'DBUSER',
        'pass' => 'DBPASS',
        'port' => 3306,
    ],
    'sqlite_path' => __DIR__ . '/../database/ruinmytrip.sqlite',
    'session_name' => 'rmt_sess',
    'security_salt' => 'CHANGE_ME_RANDOM_LONG_STRING',
];
