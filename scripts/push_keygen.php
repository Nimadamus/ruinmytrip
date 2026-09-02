<?php
/**
 * Generate a VAPID key pair for web push. Prints the three env vars to set on the web service.
 *
 *   php scripts/push_keygen.php
 *
 * Keep the private key out of the repo: it is the identity every push request is signed with.
 * Rotating it invalidates every existing subscription (browsers pin the key at subscribe time).
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('RMT_NO_AUTOSEED', true);
$GLOBALS['config'] = ['app_env' => 'cli', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip', 'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/push.php';

$k = rmt_push_keygen();
echo "VAPID_PUBLIC_KEY=" . $k['public'] . "\n";
echo "VAPID_PRIVATE_KEY=" . $k['private'] . "\n";
echo "VAPID_SUBJECT=https://ruinmytrip.com/contact\n";
