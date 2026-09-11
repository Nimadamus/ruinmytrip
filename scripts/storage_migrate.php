<?php
/**
 * Move stored images from the database into R2, one at a time, while the site stays up.
 *
 * Why this exists: photographs are currently rows in `media` with the bytes in a `data` column,
 * which works and is what is live, but a Postgres instance is the most expensive place to keep
 * binary data and the wrong place to serve it from. R2 is the destination; the only step that
 * cannot be done from a session is switching R2 on in the Cloudflare dashboard, which answers
 * `10042 Please enable R2 through the Cloudflare Dashboard` until somebody clicks it.
 *
 * The move is safe to run on a live site because nothing changes what a URL means. /media/{key}
 * keeps working for a key in either place: rmt_storage_get() looks at the row's driver and reads
 * from the bucket or the column accordingly, and pages that already point at R2's public hostname
 * get there through rmt_media_url().
 *
 * Usage:
 *   php scripts/storage_migrate.php --check            what is where, and whether R2 answers
 *   php scripts/storage_migrate.php --limit=50         move up to fifty objects
 *   php scripts/storage_migrate.php --all              move everything
 *   php scripts/storage_migrate.php --verify           re-read every migrated object and compare
 *
 * Every object is verified by hash before its bytes are dropped: the row's sha256 is compared
 * against what R2 hands back. An object that does not match is left exactly as it was.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/loadconfig.php';
$GLOBALS['config'] = rmt_load_config();
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/storage.php';

$args = array_slice($argv, 1);
$has = static fn(string $f): bool => in_array($f, $args, true);
$num = static function (string $prefix) use ($args): ?int {
    foreach ($args as $a) if (str_starts_with($a, $prefix)) return (int) substr($a, strlen($prefix));
    return null;
};

$counts = q_one("SELECT
    SUM(CASE WHEN driver = 'pg' THEN 1 ELSE 0 END) in_db,
    SUM(CASE WHEN driver = 'r2' THEN 1 ELSE 0 END) in_r2,
    COUNT(*) total FROM media") ?: ['in_db' => 0, 'in_r2' => 0, 'total' => 0];

echo "media rows: {$counts['total']} total, {$counts['in_db']} in the database, {$counts['in_r2']} in R2\n";
echo 'driver: ' . rmt_storage_driver() . ', R2 configured: ' . (rmt_r2_configured() ? 'yes' : 'no') . "\n";

if (!rmt_r2_configured()) {
    echo "\nR2 is not configured. Set these and run again:\n";
    echo "  STORAGE_DRIVER=r2 R2_ACCOUNT_ID=... R2_ACCESS_KEY_ID=... R2_SECRET_ACCESS_KEY=...\n";
    echo "  R2_BUCKET=... R2_PUBLIC_BASE_URL=https://...\n";
    exit($has('--check') ? 0 : 1);
}

/* Prove the credentials work before touching anything: write a probe object, read it back, delete
   it. A migration that discovers its credentials are wrong halfway through is worse than one that
   never started. */
$probeKey = 'migrate-probe-' . bin2hex(random_bytes(6)) . '.txt';
$probeBody = 'ruinmytrip storage probe ' . gmdate('c');
if (!rmt_r2_put($probeKey, $probeBody, 'text/plain')) {
    echo "FAILED: could not write to the bucket. Check the credentials and the bucket name.\n";
    exit(1);
}
$readBack = rmt_r2_get($probeKey);
rmt_r2_delete($probeKey);
if ($readBack !== $probeBody) {
    echo "FAILED: wrote to the bucket but read back something different. Stopping.\n";
    exit(1);
}
echo "bucket reachable: wrote, read and deleted a probe object\n";

if ($has('--check')) exit(0);

if ($has('--verify')) {
    $bad = 0; $seen = 0;
    foreach (q_all("SELECT storage_key, sha256 FROM media WHERE driver = 'r2'") as $row) {
        $seen++;
        $bytes = rmt_r2_get((string) $row['storage_key']);
        if ($bytes === null || hash('sha256', $bytes) !== (string) $row['sha256']) {
            $bad++;
            echo "  MISMATCH {$row['storage_key']}\n";
        }
    }
    echo "verified $seen object(s), $bad mismatch(es)\n";
    exit($bad ? 1 : 0);
}

$limit = $has('--all') ? 1000000 : ($num('--limit=') ?? 25);
$rows = q_all("SELECT storage_key, mime, sha256 FROM media WHERE driver = 'pg' ORDER BY id LIMIT " . (int) $limit);
if (!$rows) { echo "nothing left to move\n"; exit(0); }

$moved = 0; $failed = 0;
foreach ($rows as $row) {
    $key = (string) $row['storage_key'];
    $stored = rmt_storage_get($key);
    if (!$stored) { echo "  SKIP $key (no bytes)\n"; $failed++; continue; }

    if (!rmt_r2_put($key, $stored['bytes'], (string) $row['mime'])) { echo "  FAIL $key (put)\n"; $failed++; continue; }

    // Read it back and compare before the column is cleared. Nothing is dropped on trust.
    $check = rmt_r2_get($key);
    if ($check === null || hash('sha256', $check) !== (string) $row['sha256']) {
        echo "  FAIL $key (verify)\n";
        rmt_r2_delete($key);
        $failed++;
        continue;
    }

    $st = db()->prepare("UPDATE media SET driver = 'r2', data = NULL WHERE storage_key = ?");
    $st->execute([$key]);
    $moved++;
    echo "  moved $key\n";
}

echo "moved $moved, failed $failed\n";
exit($failed ? 1 : 0);
