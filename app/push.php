<?php
/**
 * Web Push (migration 069).
 *
 * A social site lives on the reply arriving while you still care. Email is capped at a few a day
 * and lands in a tab; a push notification lands on the lock screen the moment somebody answers,
 * which is the difference between a conversation and a message board. The PWA already installs
 * to the home screen; this is what makes it talk back.
 *
 * No library: the whole protocol is small enough to own. Payloads are encrypted per RFC 8291
 * (aes128gcm, ECDH P-256 + HKDF + AES-128-GCM) and requests are signed per RFC 8292 (VAPID,
 * ES256). Everything cryptographic is in PHP's openssl/hash extensions. The RFC 8291 Appendix A
 * test vector is in tests/push_test.php; if this file changes, that test says whether the bytes
 * still match.
 *
 * Delivery is fan-out from the notifications table: any code path that INSERTs a notification
 * gets push for free. Rows are claimed with an UPDATE guarded by pushed_at IS NULL, so two
 * concurrent requests never send the same one twice.
 *
 * Env: VAPID_PUBLIC_KEY (base64url, 65-byte uncompressed P-256 point), VAPID_PRIVATE_KEY
 * (base64url, 32-byte scalar), VAPID_SUBJECT (mailto: or https: contact). Generate with
 * scripts/push_keygen.php. Without the keys, everything here is a no-op and nothing is shown.
 */
declare(strict_types=1);

const RMT_PUSH_TTL = 86400;
const RMT_PUSH_PER_REQUEST = 12;
const RMT_PUSH_WINDOW = 900;          // seconds: a notification older than this is not pushed late

function rmt_b64u_encode(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function rmt_b64u_decode(string $s): string {
    $s = strtr(trim($s), '-_', '+/');
    return (string) base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

function rmt_push_public_key(): string { return trim((string) (getenv('VAPID_PUBLIC_KEY') ?: '')); }
function rmt_push_private_key(): string { return trim((string) (getenv('VAPID_PRIVATE_KEY') ?: '')); }
function rmt_push_subject(): string {
    $s = trim((string) (getenv('VAPID_SUBJECT') ?: ''));
    return $s !== '' ? $s : rtrim((string) cfg('app_url'), '/') . '/contact';
}
function rmt_push_enabled(): bool {
    return rmt_push_public_key() !== '' && rmt_push_private_key() !== ''
        && extension_loaded('openssl') && extension_loaded('curl') && function_exists('hash_hkdf');
}

/* ---------- key encoding ---------- */

/** SubjectPublicKeyInfo PEM for a raw uncompressed P-256 point (65 bytes, 0x04 || X || Y). */
function rmt_ec_public_pem(string $raw65): string {
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw65;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** SEC1 ECPrivateKey PEM for a raw 32-byte scalar plus its public point. */
function rmt_ec_private_pem(string $d32, string $raw65): string {
    $der = hex2bin('30770201010420') . $d32 . hex2bin('a00a06082a8648ce3d030107a144034200') . $raw65;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

/** @return array{public:string, private:string, public_raw:string, private_raw:string} base64url + raw */
function rmt_push_keygen(): array {
    $args = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    // Windows builds of PHP ship openssl.cnf beside the binary but do not find it on their own;
    // Linux finds the system one. Without a config, openssl_pkey_new() fails with "no such file".
    $cnf = (string) (getenv('OPENSSL_CONF') ?: '');
    if ($cnf === '' && is_file(dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf')) $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
    if ($cnf !== '' && is_file($cnf)) $args['config'] = $cnf;
    $k = openssl_pkey_new($args);
    if ($k === false) throw new RuntimeException('openssl could not make a P-256 key');
    $d = openssl_pkey_get_details($k);
    $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $priv = str_pad($d['ec']['d'], 32, "\0", STR_PAD_LEFT);
    return ['public' => rmt_b64u_encode($pub), 'private' => rmt_b64u_encode($priv), 'public_raw' => $pub, 'private_raw' => $priv];
}

/* ---------- RFC 8291 content encryption ---------- */

function rmt_push_ecdh(string $privPem, string $peerRaw65): string {
    $priv = openssl_pkey_get_private($privPem);
    $pub = openssl_pkey_get_public(rmt_ec_public_pem($peerRaw65));
    if ($priv === false || $pub === false) throw new RuntimeException('bad EC key');
    $s = openssl_pkey_derive($pub, $priv, 32);
    if ($s === false || strlen($s) !== 32) throw new RuntimeException('ECDH failed');
    return $s;
}

/**
 * Encrypt one message for one subscription. Returns the aes128gcm body: header || ciphertext.
 * $asPrivPem/$asPubRaw/$salt are injectable so the RFC test vector can be reproduced exactly.
 */
function rmt_push_encrypt(string $plaintext, string $uaPublicB64u, string $authB64u,
                          ?string $asPrivPem = null, ?string $asPubRaw = null, ?string $salt = null): string {
    $ua = rmt_b64u_decode($uaPublicB64u);
    $auth = rmt_b64u_decode($authB64u);
    if (strlen($ua) !== 65 || $ua[0] !== "\x04" || strlen($auth) !== 16) throw new RuntimeException('bad subscription keys');
    if ($asPrivPem === null || $asPubRaw === null) {
        $k = rmt_push_keygen();
        $asPubRaw = $k['public_raw'];
        $asPrivPem = rmt_ec_private_pem($k['private_raw'], $asPubRaw);
    }
    $salt ??= random_bytes(16);
    if (strlen($salt) !== 16) throw new RuntimeException('salt must be 16 bytes');
    if (strlen($plaintext) > 3993) throw new RuntimeException('payload too large for one record');

    $ecdh  = rmt_push_ecdh($asPrivPem, $ua);
    $ikm   = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $ua . $asPubRaw, $auth);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $tag = '';
    $ct = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ct === false) throw new RuntimeException('AES-GCM failed');
    return $salt . pack('N', 4096) . chr(65) . $asPubRaw . $ct . $tag;
}

/* ---------- RFC 8292 VAPID ---------- */

/** ECDSA DER (SEQUENCE of two INTEGERs) to the fixed 64-byte r||s JOSE form. */
function rmt_der_sig_to_raw(string $der): string {
    $pos = 2;
    if ((ord($der[1]) & 0x80) !== 0) $pos = 2 + (ord($der[1]) & 0x7f);
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        if (($der[$pos] ?? '') !== "\x02") throw new RuntimeException('bad DER signature');
        $len = ord($der[$pos + 1]); $pos += 2;
        $int = ltrim(substr($der, $pos, $len), "\0"); $pos += $len;
        if (strlen($int) > 32) throw new RuntimeException('bad DER integer');
        $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
    }
    return $out;
}

function rmt_push_vapid_jwt(string $audience, ?int $now = null): string {
    $now ??= time();
    $h = rmt_b64u_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']) ?: '');
    $c = rmt_b64u_encode(json_encode(['aud' => $audience, 'exp' => $now + 12 * 3600, 'sub' => rmt_push_subject()]) ?: '');
    $pem = rmt_ec_private_pem(rmt_b64u_decode(rmt_push_private_key()), rmt_b64u_decode(rmt_push_public_key()));
    $der = '';
    if (!openssl_sign("$h.$c", $der, $pem, OPENSSL_ALGO_SHA256)) throw new RuntimeException('VAPID sign failed');
    return "$h.$c." . rmt_b64u_encode(rmt_der_sig_to_raw($der));
}

/* ---------- subscriptions ---------- */

function rmt_push_subscribe(int $uid, string $endpoint, string $p256dh, string $auth, string $ua = ''): bool {
    $endpoint = trim($endpoint);
    if (strlen($endpoint) > 2000 || !preg_match('#^https://[^\s]+$#', $endpoint)) return false;
    $pub = rmt_b64u_decode($p256dh); $sec = rmt_b64u_decode($auth);
    if (strlen($pub) !== 65 || $pub[0] !== "\x04" || strlen($sec) !== 16) return false;
    $now = date('Y-m-d H:i:s');
    q_run('DELETE FROM push_subscriptions WHERE endpoint=?', [$endpoint]);
    q_run('INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, created_at, last_ok_at)
           VALUES (?,?,?,?,?,?,?)', [$uid, $endpoint, rmt_b64u_encode($pub), rmt_b64u_encode($sec), mb_substr($ua, 0, 200), $now, $now]);
    return true;
}

function rmt_push_unsubscribe(string $endpoint, ?int $uid = null): void {
    if ($uid) q_run('DELETE FROM push_subscriptions WHERE endpoint=? AND user_id=?', [trim($endpoint), $uid]);
    else      q_run('DELETE FROM push_subscriptions WHERE endpoint=?', [trim($endpoint)]);
}

/** @return list<array> */
function rmt_push_subscriptions(int $uid): array {
    return q_all('SELECT * FROM push_subscriptions WHERE user_id=? ORDER BY id', [$uid]);
}

/* ---------- delivery ---------- */

/**
 * POST one encrypted payload to one endpoint. Returns the HTTP status (0 on transport failure).
 * $GLOBALS['rmt_push_transport'] may hold a callable(endpoint, headers, body): int for tests.
 */
function rmt_push_deliver(array $sub, array $payload): int {
    $body = rmt_push_encrypt(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', (string) $sub['p256dh'], (string) $sub['auth']);
    $u = parse_url((string) $sub['endpoint']);
    $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: ' . RMT_PUSH_TTL,
        'Urgency: normal',
        'Authorization: vapid t=' . rmt_push_vapid_jwt($aud) . ', k=' . rmt_push_public_key(),
    ];
    if (isset($GLOBALS['rmt_push_transport']) && is_callable($GLOBALS['rmt_push_transport'])) {
        return (int) ($GLOBALS['rmt_push_transport'])((string) $sub['endpoint'], $headers, $body);
    }
    $ch = curl_init((string) $sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_HTTPHEADER => $headers,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $code;
}

/**
 * The words for one notification row, or null for a type that should not buzz a phone.
 * Mirrors views/notifications.php: same facts, shorter.
 *
 * @return ?array{title:string, body:string, url:string, tag:string}
 */
function rmt_push_line(array $n): ?array {
    $actor = $n['actor_id'] ? q_one("SELECT username FROM users WHERE id=? AND status='active'", [(int) $n['actor_id']]) : null;
    $who = $actor ? '@' . $actor['username'] : 'Someone';
    $type = (string) $n['type'];
    $tt = (string) ($n['target_type'] ?? ''); $tid = (int) ($n['target_id'] ?? 0);
    $noun = ['trip' => 'trip story', 'review' => 'review', 'guide' => 'guide', 'blog_post' => 'blog post',
             'meetup' => 'meetup', 'collection' => 'list', 'post' => 'post'][$tt] ?? 'post';
    $url = null; $body = null;
    switch ($type) {
        case 'follow':      $body = "$who started following you."; $url = $actor ? url('u/' . $actor['username']) : null; break;
        case 'compliment':  $body = "$who sent you a compliment."; $url = url('notifications'); break;
        case 'comment':     $body = "$who replied to your $noun."; $url = rmt_notification_target_url($tt, $tid, (int) $n['user_id']); break;
        case 'mention':     $body = "$who mentioned you in a $noun."; $url = rmt_notification_target_url($tt, $tid, (int) $n['user_id']); break;
        case 'like':        $body = "$who liked your $noun."; $url = rmt_notification_target_url($tt, $tid, (int) $n['user_id']); break;
        case 'repost':      $body = "$who reposted you."; $url = rmt_notification_target_url('post', $tid, (int) $n['user_id']); break;
        case 'message':     $body = "$who sent you a message."; $url = rmt_notification_target_url($tt, $tid, (int) $n['user_id']); break;
        case 'going':       $body = "$who shared upcoming travel dates."; $url = rmt_notification_target_url('going', $tid, (int) $n['user_id']); break;
        case 'invite_joined': $body = "$who joined from your invite link. Say hi."; $url = $actor ? url('u/' . $actor['username']) : url('invite'); break;
        case 'trip_match':
            $dest = q_one('SELECT d.name FROM going g JOIN destinations d ON d.id=g.destination_id WHERE g.id=?', [$tid])['name'] ?? null;
            $body = $dest ? "$who will be in $dest while you are." : "$who has dates that overlap yours.";
            $url = url('matches'); break;
        case 'meetup_rsvp': case 'meetup_changed': case 'meetup_cancelled': case 'meetup_nearby':
            $title = q_one('SELECT title FROM meetups WHERE id=?', [$tid])['title'] ?? null;
            $what = $title ? '"' . $title . '"' : 'a meetup';
            $body = ['meetup_rsvp' => "$who is going to " . ($title ? $what : 'your meetup') . '.',
                     'meetup_changed' => "The time changed for $what.",
                     'meetup_cancelled' => "Cancelled: $what.",
                     'meetup_nearby' => "$who is hosting $what while you are in town."][$type];
            $url = url('meetup/' . $tid); break;
        default: return null;
    }
    return ['title' => 'RuinMyTrip', 'body' => $body, 'url' => $url ?: url('notifications'), 'tag' => $type . ':' . $tt . ':' . $tid];
}

/**
 * Push every recent, unpushed notification whose recipient has a device. Claims each row with
 * a guarded UPDATE so concurrent requests split the work rather than duplicate it. Returns the
 * number of successful deliveries.
 */
function rmt_push_flush(int $limit = RMT_PUSH_PER_REQUEST): int {
    if (!rmt_push_enabled()) return 0;
    $since = date('Y-m-d H:i:s', time() - RMT_PUSH_WINDOW);
    $rows = q_all("SELECT n.* FROM notifications n
                    WHERE n.pushed_at IS NULL AND n.created_at >= ?
                      AND EXISTS (SELECT 1 FROM push_subscriptions s WHERE s.user_id = n.user_id)
                    ORDER BY n.id LIMIT " . (int) $limit, [$since]);
    $sent = 0;
    $now = date('Y-m-d H:i:s');
    foreach ($rows as $n) {
        $st = db()->prepare('UPDATE notifications SET pushed_at=? WHERE id=? AND pushed_at IS NULL');
        $st->execute([$now, (int) $n['id']]);
        if ($st->rowCount() !== 1) continue;                     // another request owns it
        $line = rmt_push_line($n);
        if (!$line) continue;
        foreach (rmt_push_subscriptions((int) $n['user_id']) as $sub) {
            try { $code = rmt_push_deliver($sub, $line); }
            catch (\Throwable $e) { error_log('[push] ' . $e->getMessage()); $code = 0; }
            if ($code === 404 || $code === 410) { rmt_push_unsubscribe((string) $sub['endpoint']); continue; }
            if ($code >= 200 && $code < 300) {
                $sent++;
                q_run('UPDATE push_subscriptions SET last_ok_at=? WHERE id=?', [$now, (int) $sub['id']]);
            } else {
                q_run('UPDATE push_subscriptions SET failed_at=? WHERE id=?', [$now, (int) $sub['id']]);
            }
        }
    }
    return $sent;
}

/** Shutdown hook: release the session lock first so the sender never blocks the user's next click. */
function rmt_push_flush_at_shutdown(): void {
    if (!rmt_push_enabled()) return;
    try {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        rmt_push_flush();
    } catch (\Throwable $e) {
        error_log('[push] flush: ' . $e->getMessage());
    }
}
