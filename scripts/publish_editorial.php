<?php
declare(strict_types=1);

/**
 * Publish the editorial layer from database/editorial/*.json.
 *
 * This is NOT the demo seeder. rmt_seed_data() fabricates members and reviews and is hard-blocked
 * in production for good reason; this script is its opposite. It publishes real, researched,
 * clearly labelled editorial content under one official account, so it is allowed to run against
 * production — but only with --apply, and only after --check passes.
 *
 * Guarantees this script enforces:
 *   - Exactly one editorial account, role='editorial', with a random unknown password. Nobody can
 *     log in as it; it exists to own bylines.
 *   - Editorial reviews carry NO visited_on and verified=0. The team did not necessarily go.
 *   - Idempotent. Re-running updates the same rows (matched on destination + author) instead of
 *     stacking duplicates, so content can be corrected without a mess.
 *   - Every photo must already have credit + licence + source recorded in the JSON, or the
 *     destination is refused. Unattributed use of a CC image is a licence breach.
 *
 * Usage:
 *   php scripts/publish_editorial.php --check          validate the JSON, touch nothing
 *   php scripts/publish_editorial.php                  dry run against the configured DB
 *   php scripts/publish_editorial.php --apply          write
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$args   = array_slice($argv, 1);
$apply  = in_array('--apply', $args, true);
$check  = in_array('--check', $args, true);
$dir    = BASE_PATH . '/database/editorial';

function out(string $s): void { echo $s . PHP_EOL; }
function fail(string $s): never { fwrite(STDERR, 'ERROR: ' . $s . PHP_EOL); exit(1); }

/* ---------------- load + validate ---------------- */

$files = glob($dir . '/*.json') ?: [];
if (!$files) fail("no editorial content found in {$dir}");

$items = [];
foreach ($files as $f) {
    $json = json_decode((string) file_get_contents($f), true);
    if (!is_array($json) || !isset($json['destinations'])) fail("malformed JSON: {$f}");
    foreach ($json['destinations'] as $d) $items[] = $d + ['_file' => basename($f)];
}

$required = ['slug','name','country','summary','headline','body','rating','safety_rating',
             'value_rating','what_great','what_ruined','tips','photo_url','photo_credit',
             'photo_license','photo_source_url','guide'];
$problems = [];
$seen = [];
foreach ($items as $i => $d) {
    $label = $d['slug'] ?? "#{$i}";
    foreach ($required as $k) {
        if (!isset($d[$k]) || $d[$k] === '' || $d[$k] === []) $problems[] = "{$label}: missing {$k}";
    }
    if (isset($seen[$d['slug'] ?? ''])) $problems[] = "{$label}: duplicate slug";
    $seen[$d['slug'] ?? ''] = true;

    foreach (['rating','safety_rating','value_rating'] as $k) {
        $v = (int) ($d[$k] ?? 0);
        if ($v < 1 || $v > 5) $problems[] = "{$label}: {$k} must be 1-5, got {$v}";
    }
    if (mb_strlen((string) ($d['headline'] ?? '')) > RMT_REVIEW_TITLE_MAX) $problems[] = "{$label}: headline too long";
    if (mb_strlen((string) ($d['body'] ?? '')) < 400) $problems[] = "{$label}: body under 400 chars, too thin to publish";
    if (isset($d['tips']) && count((array) $d['tips']) < 4) $problems[] = "{$label}: fewer than 4 tips";
    // No em dashes anywhere in published copy.
    foreach (['summary','headline','body','what_great','what_ruined'] as $k) {
        if (str_contains((string) ($d[$k] ?? ''), "\u{2014}")) $problems[] = "{$label}: em dash in {$k}";
    }
    // An editorial review must never claim a visit.
    if (!empty($d['visited_on'])) $problems[] = "{$label}: editorial content must not carry visited_on";
    foreach (['photo_url','photo_source_url'] as $k) {
        if (!filter_var($d[$k] ?? '', FILTER_VALIDATE_URL)) $problems[] = "{$label}: {$k} is not a URL";
    }
    $g = $d['guide'] ?? [];
    foreach (['title','summary','body'] as $k) {
        if (empty($g[$k])) $problems[] = "{$label}: guide.{$k} missing";
    }
    if (mb_strlen((string) ($g['body'] ?? '')) < 600) $problems[] = "{$label}: guide body too thin";
}

if ($problems) {
    out('VALIDATION FAILED (' . count($problems) . '):');
    foreach ($problems as $p) out('  - ' . $p);
    exit(1);
}
out('Validated ' . count($items) . ' destinations from ' . count($files) . ' file(s). No problems.');
if ($check) exit(0);

/* ---------------- write ---------------- */

$driver = $GLOBALS['config']['db_driver'];
$env    = $GLOBALS['config']['app_env'];
out(sprintf('Target: %s / %s  |  mode: %s', $env, $driver, $apply ? 'APPLY' : 'DRY RUN'));

$now = date('Y-m-d H:i:s');
$pdo = db();

/**
 * Copy a licensed photograph into our own media store and return its local URL.
 *
 * Hot-linking Wikimedia is discouraged by the Foundation and leaves every destination page
 * dependent on someone else's uptime and URL scheme (their thumbnail sizes are already
 * allow-listed and 400 anything else). Importing once, re-encoding through GD, and serving from
 * /media/{key} fixes both. The key is derived from the source URL, so re-running the publisher
 * reuses the stored image instead of downloading it again.
 *
 * Attribution is unaffected: credit, licence and source link are stored on the destination row
 * and rendered under the image.
 */
function rmt_editorial_import_photo(string $url, int $ownerId, bool $apply): ?string {
    $key = md5($url) . '.jpg';
    // Store a ROOT-RELATIVE path, never an absolute URL. rmt_media_url() bakes in whatever
    // app_url the publishing process happened to have, so an absolute value published from a
    // local shell points every hero image at localhost once it reaches production.
    $path = '/media/' . $key;
    if (q_one('SELECT storage_key FROM media WHERE storage_key = ?', [$key])) return $path;
    if (!$apply) return $url; // dry run: no download, no write

    $ctx = stream_context_create(['http' => [
        'timeout' => 30,
        'header'  => "User-Agent: RuinMyTrip/1.0 (https://ruinmytrip.com; editorial image import)\r\n",
    ]]);
    $bytes = @file_get_contents($url, false, $ctx);
    if ($bytes === false || strlen($bytes) < 1024) { out("    ! photo download failed: {$url}"); return null; }

    $img = @imagecreatefromstring($bytes);
    if (!$img) { out("    ! photo not decodable: {$url}"); return null; }
    $w = imagesx($img); $h = imagesy($img);
    ob_start(); imagejpeg($img, null, 82); $jpeg = (string) ob_get_clean();
    imagedestroy($img);

    $ok = rmt_storage_put($key, $jpeg, 'image/jpeg', $ownerId, $w, $h, hash('sha256', $jpeg));
    if (!$ok) { out("    ! photo store failed: {$url}"); return null; }
    out(sprintf('    photo imported %dx%d, %dKB', $w, $h, strlen($jpeg) / 1024));
    return $path;
}

/** Text to safe paragraph HTML. Editorial bodies are plain text; guide_show prints body as HTML. */
function rmt_paras(string $text): string {
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $p) {
        $p = trim($p);
        if ($p !== '') $out .= '<p>' . e($p) . '</p>' . "\n";
    }
    return $out;
}

$log = [];
$run = function (string $sql, array $args) use ($apply, &$log) {
    $log[] = preg_replace('/\s+/', ' ', substr($sql, 0, 60)) . '…';
    if ($apply) return q_run($sql, $args);
    return '';
};

if ($apply) $pdo->beginTransaction();
try {
    /* editorial account */
    $ed = q_one('SELECT * FROM users WHERE role = ?', [RMT_EDITORIAL_ROLE]);
    if (!$ed) {
        $ed = q_one('SELECT * FROM users WHERE username = ?', [RMT_EDITORIAL_USERNAME]);
        if ($ed) fail('username ' . RMT_EDITORIAL_USERNAME . ' is taken by a non-editorial account (id ' . $ed['id'] . ')');
    }
    if (!$ed) {
        // Random password nobody holds: this account is a byline, not a login.
        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        $uid = (int) $run('INSERT INTO users (username, email, password_hash, role, birthdate, status, created_at, email_verified_at)
                           VALUES (?,?,?,?,?,?,?,?)',
                          [RMT_EDITORIAL_USERNAME, 'editorial@ruinmytrip.com', $hash, RMT_EDITORIAL_ROLE,
                           '1990-01-01', 'active', $now, $now]);
        out($apply ? "created editorial account id {$uid}" : 'would create editorial account');
        if ($apply) {
            $run('INSERT INTO profiles (user_id, display_name, bio, credibility_score) VALUES (?,?,?,0)',
                 [$uid, 'RuinMyTrip Editorial',
                  'The RuinMyTrip team. We research destinations from published and official sources and publish the result as clearly labelled editorial content. We do not post traveler reviews, and our ratings are never counted in a destination\'s community score.']);
        }
    } else {
        $uid = (int) $ed['id'];
        out("using existing editorial account id {$uid} (@{$ed['username']})");
    }
    if (!$apply && !isset($uid)) $uid = 0;

    foreach ($items as $d) {
        $dest = q_one('SELECT * FROM destinations WHERE slug = ?', [$d['slug']]);
        if (!$dest) { out("  SKIP {$d['slug']}: no such destination row"); continue; }
        $did = (int) $dest['id'];
        out("  {$d['slug']} (destination {$did})");

        /* destination: summary + attributed hero photo, served from our own media store */
        $hero = rmt_editorial_import_photo((string) $d['photo_url'], $uid, $apply) ?? $d['photo_url'];
        $run('UPDATE destinations SET summary = ?, hero_url = ?, hero_credit = ?, hero_license = ?, hero_source_url = ?
              WHERE id = ?',
             [$d['summary'], $hero, $d['photo_credit'], $d['photo_license'], $d['photo_source_url'], $did]);

        /* editorial review: one per destination, matched on author + destination */
        $existing = q_one('SELECT id FROM reviews WHERE user_id = ? AND destination_id = ?', [$uid, $did]);
        $slug = mb_substr(slugify((string) $d['headline']), 0, 70);
        if ($existing) {
            $run("UPDATE reviews SET subject_type='destination', subject_name=?, rating=?, title=?, body=?,
                    what_great=?, what_ruined=?, safety_rating=?, value_rating=?, slug=?, visited_on=NULL,
                    verified=0, status='published', updated_at=? WHERE id=?",
                 [$d['name'], (int)$d['rating'], $d['headline'], $d['body'], $d['what_great'], $d['what_ruined'],
                  (int)$d['safety_rating'], (int)$d['value_rating'], $slug, $now, (int)$existing['id']]);
            out("    review updated (id {$existing['id']})");
        } else {
            $rid = $run("INSERT INTO reviews (user_id, destination_id, subject_type, subject_name, rating, title, body,
                            what_great, what_ruined, safety_rating, value_rating, slug, visited_on, verified, status,
                            created_at, updated_at)
                         VALUES (?,?,'destination',?,?,?,?,?,?,?,?,?,NULL,0,'published',?,?)",
                        [$uid, $did, $d['name'], (int)$d['rating'], $d['headline'], $d['body'], $d['what_great'],
                         $d['what_ruined'], (int)$d['safety_rating'], (int)$d['value_rating'], $slug, $now, $now]);
            out('    review created' . ($apply ? " (id {$rid})" : ''));
        }

        /* tips: replace the editorial set for this destination */
        if ($apply) $pdo->prepare('DELETE FROM destination_tips WHERE destination_id = ?')->execute([$did]);
        foreach (array_values((array) $d['tips']) as $i => $tip) {
            $run('INSERT INTO destination_tips (destination_id, body, sort) VALUES (?,?,?)', [$did, $tip, $i]);
        }
        out('    ' . count((array) $d['tips']) . ' tips');

        /* guide */
        $g = $d['guide'];
        $gslug = $g['slug'] ?? slugify((string) $g['title']);
        $body  = rmt_paras((string) $g['body']);
        $have  = q_one('SELECT id FROM guides WHERE slug = ?', [$gslug]);
        if ($have) {
            $run("UPDATE guides SET user_id=?, destination_id=?, title=?, summary=?, body=?, cover_url=?,
                    premium=0, status='published', updated_at=? WHERE id=?",
                 [$uid, $did, $g['title'], $g['summary'], $body, $hero, $now, (int)$have['id']]);
            out("    guide updated (/g/{$gslug})");
        } else {
            $run("INSERT INTO guides (user_id, destination_id, slug, title, summary, body, cover_url, premium, status, created_at, updated_at)
                  VALUES (?,?,?,?,?,?,?,0,'published',?,?)",
                 [$uid, $did, $gslug, $g['title'], $g['summary'], $body, $hero, $now, $now]);
            out("    guide created (/g/{$gslug})");
        }
    }

    if ($apply) { $pdo->commit(); out('COMMITTED.'); }
    else out('DRY RUN complete, nothing written. Re-run with --apply.');
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    fail($e->getMessage());
}
