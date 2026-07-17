<?php
/**
 * PRODUCTION config template for RuinMyTrip on Namecheap cPanel (account: chatnpm).
 * On the server, copy this to  app/config.php  (gitignored) and fill in the real DB
 * credentials created in cPanel > MySQL Databases. NEVER commit the filled-in file.
 *
 * App files live OUTSIDE the web root:  /home/chatnpm/ruinmytrip.com/{app,views,database}
 * Web root (addon docroot):             /home/chatnpm/ruinmytrip.com/public
 */
return [
    'app_env'   => 'production',
    'app_url'   => 'https://ruinmytrip.com',
    'app_name'  => 'RuinMyTrip',
    'db_driver' => 'mysql',
    'mysql'     => [
        'host' => 'localhost',
        'name' => 'chatnpm_ruinmytrip',       // cPanel prefixes with account name
        'user' => 'chatnpm_rmt',              // least-privilege user for this DB only
        'pass' => 'SET_ON_SERVER_ONLY',       // paste the generated strong password here, on the server
        'port' => 3306,
    ],
    'sqlite_path' => __DIR__ . '/../database/ruinmytrip.sqlite', // unused in production
    'session_name' => 'rmt_sess',
    'security_salt' => 'REPLACE_WITH_64_RANDOM_HEX_CHARS',
];
