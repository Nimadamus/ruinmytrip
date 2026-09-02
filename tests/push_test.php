<?php
/**
 * Web push: RFC 8291 test vector, VAPID signature verifies, subscription validation, flush claims
 * rows once and drops dead endpoints.
 *
 *   php tests/push_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/push.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "FAIL: $msg\n"; } }

/* ---- RFC 8291 Appendix A ---- */
$uaPub  = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
$uaPriv = 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94';
$auth   = 'BTBZMqHH6r4Tts7J_aSIgg';
$asPub  = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';
$asPriv = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';
$salt   = 'DGv6ra1nlYgDCS1FRnbzlw';
$expect = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

$asPem = rmt_ec_private_pem(rmt_b64u_decode($asPriv), rmt_b64u_decode($asPub));
$body = rmt_push_encrypt('When I grow up, I want to be a watermelon', $uaPub, $auth, $asPem, rmt_b64u_decode($asPub), rmt_b64u_decode($salt));
ok(rmt_b64u_encode($body) === $expect, 'RFC 8291 test vector reproduced byte for byte');
ok(strlen($body) === 16 + 4 + 1 + 65 + strlen('When I grow up, I want to be a watermelon') + 1 + 16, 'aes128gcm body length: header + plaintext + pad + tag');

// Round trip with the UA private key proves the recipient side agrees on the shared secret.
$uaPem = rmt_ec_private_pem(rmt_b64u_decode($uaPriv), rmt_b64u_decode($uaPub));
$fresh = rmt_push_encrypt('hello phone', $uaPub, $auth);
$saltF = substr($fresh, 0, 16); $asPubF = substr($fresh, 21, 65); $ctF = substr($fresh, 86);
$ecdh = rmt_push_ecdh($uaPem, $asPubF);
$ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . rmt_b64u_decode($uaPub) . $asPubF, rmt_b64u_decode($auth));
$cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $saltF);
$nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $saltF);
$pt = openssl_decrypt(substr($ctF, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ctF, -16));
ok($pt === "hello phone\x02", 'fresh ephemeral key: recipient decrypts to plaintext + delimiter');

/* ---- VAPID ---- */
$k = rmt_push_keygen();
putenv('VAPID_PUBLIC_KEY=' . $k['public']);
putenv('VAPID_PRIVATE_KEY=' . $k['private']);
putenv('VAPID_SUBJECT=mailto:ops@example.test');
ok(rmt_push_enabled(), 'enabled once keys are present');
ok(strlen($k['public_raw']) === 65 && $k['public_raw'][0] === "\x04" && strlen($k['private_raw']) === 32, 'keygen shapes');
$jwt = rmt_push_vapid_jwt('https://fcm.googleapis.com', 1700000000);
[$h, $c, $sig] = explode('.', $jwt);
$claims = json_decode(rmt_b64u_decode($c), true);
ok(json_decode(rmt_b64u_decode($h), true) === ['typ' => 'JWT', 'alg' => 'ES256'], 'JWT header');
ok($claims['aud'] === 'https://fcm.googleapis.com' && $claims['exp'] === 1700000000 + 43200 && $claims['sub'] === 'mailto:ops@example.test', 'JWT claims');
$raw = rmt_b64u_decode($sig);
ok(strlen($raw) === 64, 'signature is raw r||s');
// Back to DER and verify with the public key: proves the raw conversion and the key encoding.
$int = function (string $b): string { $b = ltrim($b, "\0"); if ($b === '' ) $b = "\0"; if (ord($b[0]) & 0x80) $b = "\0" . $b; return "\x02" . chr(strlen($b)) . $b; };
$der = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
$der = "\x30" . chr(strlen($der)) . $der;
ok(openssl_verify("$h.$c", $der, openssl_pkey_get_public(rmt_ec_public_pem($k['public_raw'])), OPENSSL_ALGO_SHA256) === 1, 'JWT signature verifies with the public key');

/* ---- subscriptions + flush ---- */
$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT, role TEXT)');
$pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT, actor_id INT, target_type TEXT, target_id INT, read_at TEXT, created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/069_push_subscriptions.sqlite.sql'));
$pdo->exec("CREATE TABLE going (id INTEGER PRIMARY KEY, destination_id INT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT)");
$pdo->exec("CREATE TABLE meetups (id INTEGER PRIMARY KEY, title TEXT)");
$pdo->exec("INSERT INTO users VALUES (1,'ana','active','user'),(2,'ben','active','user'),(3,'cy','active','user')");
function rmt_notification_target_url(string $type, int $id, int $forUserId = 0): ?string { return 'https://example.test/' . $type . '/' . $id; }

ok(!rmt_push_subscribe(1, 'http://insecure.example/x', $uaPub, $auth), 'http endpoint refused');
ok(!rmt_push_subscribe(1, 'https://push.example/x', 'AAAA', $auth), 'bad p256dh refused');
ok(!rmt_push_subscribe(1, 'https://push.example/x', $uaPub, 'short'), 'bad auth refused');
ok(rmt_push_subscribe(1, 'https://push.example/dev-a', $uaPub, $auth, 'UA'), 'ana device a');
ok(rmt_push_subscribe(1, 'https://push.example/dev-a', $uaPub, $auth, 'UA2'), 'same endpoint again replaces');
ok(count(rmt_push_subscriptions(1)) === 1 && rmt_push_subscriptions(1)[0]['user_agent'] === 'UA2', 'one row per endpoint');
ok(rmt_push_subscribe(1, 'https://push.example/dev-dead', $uaPub, $auth), 'ana device dead');
ok(rmt_push_subscribe(2, 'https://push.example/ben', $uaPub, $auth), 'ben device');

$now = date('Y-m-d H:i:s');
$pdo->exec("INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at) VALUES
  (1,'comment',2,'post',7,'$now'), (2,'follow',1,NULL,NULL,'$now'), (3,'like',1,'post',7,'$now'),
  (1,'comment',2,'post',8,'2000-01-01 00:00:00'), (1,'unknown_kind',2,'post',9,'$now')");

$calls = [];
$GLOBALS['rmt_push_transport'] = function (string $endpoint, array $headers, string $body) use (&$calls): int {
    $calls[] = $endpoint;
    $auth = implode("\n", $headers);
    if (!str_contains($auth, 'Authorization: vapid t=') || !str_contains($auth, 'Content-Encoding: aes128gcm')) return 400;
    if (strlen($body) < 87) return 400;
    return str_ends_with($endpoint, 'dev-dead') ? 410 : 201;
};
$sent = rmt_push_flush();
ok($sent === 2, "two deliveries succeeded (got $sent)");
sort($calls);
ok($calls === ['https://push.example/ben', 'https://push.example/dev-a', 'https://push.example/dev-dead'], 'every live device of a recipient with rows was tried');
ok(q_one("SELECT 1 x FROM push_subscriptions WHERE endpoint='https://push.example/dev-dead'") === null, '410 endpoint deleted');
ok((int) q_one("SELECT COUNT(*) c FROM notifications WHERE pushed_at IS NULL")['c'] === 2, 'old row and no-device row left unclaimed; claimed rows marked');
ok(q_one("SELECT pushed_at FROM notifications WHERE type='unknown_kind'")['pushed_at'] !== null, 'unknown type is claimed (never retried) but not sent');
$calls = [];
ok(rmt_push_flush() === 0 && $calls === [], 'second flush sends nothing: rows were claimed');

/* ---- lines ---- */
$pdo->exec("INSERT INTO destinations VALUES (1,'Lisbon')"); $pdo->exec("INSERT INTO going VALUES (5,1)"); $pdo->exec("INSERT INTO meetups VALUES (9,'Sunset walk')");
$l = rmt_push_line(['user_id' => 1, 'type' => 'trip_match', 'actor_id' => 2, 'target_type' => 'going', 'target_id' => 5]);
ok($l['body'] === '@ben will be in Lisbon while you are.' && str_ends_with($l['url'], '/matches'), 'match line');
$l = rmt_push_line(['user_id' => 1, 'type' => 'meetup_cancelled', 'actor_id' => 2, 'target_type' => 'meetup', 'target_id' => 9]);
ok($l['body'] === 'Cancelled: "Sunset walk".' && str_ends_with($l['url'], '/meetup/9'), 'meetup line');
$l = rmt_push_line(['user_id' => 1, 'type' => 'invite_joined', 'actor_id' => 2, 'target_type' => 'user', 'target_id' => 2]);
ok(str_starts_with($l['body'], '@ben joined from your invite link') && str_ends_with($l['url'], '/u/ben'), 'invite line');
ok(rmt_push_line(['user_id' => 1, 'type' => 'digest', 'actor_id' => null, 'target_type' => '', 'target_id' => 0]) === null, 'unknown type: no push');

putenv('VAPID_PUBLIC_KEY'); putenv('VAPID_PRIVATE_KEY');
ok(!rmt_push_enabled() && rmt_push_flush() === 0, 'without keys everything is a no-op');

echo "push_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
