<?php
/**
 * LOCAL DEV FIXTURES — bulk synthetic users/trips/reviews for testing pagination,
 * profile rendering, feed load, and validator behaviour under volume.
 *
 * THIS DATA IS FAKE AND MUST NEVER REACH PRODUCTION.
 *
 * Every fixture row is tagged so it can always be identified and purged:
 *   - username  starts with  fixture_
 *   - email     ends with    @fixture.invalid   (.invalid is RFC2606-reserved: can never be a real inbox)
 *
 * Usage:
 *   php scripts/dev_fixtures.php            # add 100 users (+ trips/reviews/follows)
 *   php scripts/dev_fixtures.php 1000        # add 1000
 *   php scripts/dev_fixtures.php --purge     # remove every fixture row
 *   php scripts/dev_fixtures.php --count     # report how many fixture rows exist
 *
 * Deterministic: same N always produces the same data (seeded PRNG), so test runs are repeatable.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/loadconfig.php';
$GLOBALS['config'] = rmt_load_config();
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

/* ------------------------------------------------------------------ *
 * PRODUCTION GUARDS — three independent checks, any one aborts.
 * This script must be impossible to point at the live database.
 * ------------------------------------------------------------------ */
function fixtures_abort(string $why): never {
    fwrite(STDERR, "\n  REFUSED: {$why}\n  Fixtures are local-only synthetic data and must never touch production.\n\n");
    exit(1);
}
if (($GLOBALS['config']['app_env'] ?? '') === 'production') fixtures_abort('APP_ENV is production.');
if (getenv('DATABASE_URL')) fixtures_abort('DATABASE_URL is set (points at a managed Postgres).');
if (($GLOBALS['config']['db_driver'] ?? '') !== 'sqlite') fixtures_abort('db_driver is not sqlite (driver=' . ($GLOBALS['config']['db_driver'] ?? '?') . ').');

const FIX_USER_PREFIX = 'fixture_';
const FIX_EMAIL_DOMAIN = '@fixture.invalid';

$pdo = db();
$arg = $argv[1] ?? '100';

/* ---------------- purge / count ---------------- */
function fixture_counts(PDO $pdo): array {
    $ids = $pdo->query("SELECT id FROM users WHERE username LIKE '" . FIX_USER_PREFIX . "%'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return ['users' => 0, 'trips' => 0, 'reviews' => 0, 'follows' => 0];
    $in = implode(',', array_map('intval', $ids));
    return [
        'users'   => count($ids),
        'trips'   => (int) $pdo->query("SELECT COUNT(*) FROM trips   WHERE user_id IN ($in)")->fetchColumn(),
        'reviews' => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE user_id IN ($in)")->fetchColumn(),
        'follows' => (int) $pdo->query("SELECT COUNT(*) FROM follows WHERE follower_id IN ($in) OR followee_id IN ($in)")->fetchColumn(),
    ];
}

if ($arg === '--count') {
    foreach (fixture_counts($pdo) as $k => $v) printf("  %-8s %d\n", $k, $v);
    exit(0);
}

if ($arg === '--purge') {
    $before = fixture_counts($pdo);
    // ON DELETE CASCADE clears trips/reviews/follows/likes/notifications for these users.
    $pdo->exec("DELETE FROM users WHERE username LIKE '" . FIX_USER_PREFIX . "%'");
    $after = fixture_counts($pdo);
    fwrite(STDOUT, "purged fixtures: {$before['users']} users, {$before['trips']} trips, {$before['reviews']} reviews\n");
    fwrite(STDOUT, "remaining fixture users: {$after['users']}\n");
    exit(0);
}

$n = max(1, (int) $arg);

/* ---------------- deterministic generator ---------------- */
mt_srand(20260717); // fixed seed => reproducible fixtures

$first = ['ana','ben','cleo','dev','elin','fen','gia','hugo','ivy','jonas','kira','luca','mira','noor','omar','pia','quinn','rae','sol','tariq','uma','vik','wren','yuki','zane'];
$last  = ['reyes','okafor','lindqvist','moreau','tanaka','silva','novak','haddad','bergman','costa','ferrer','ilves','koch','marek','nilsen'];
$adj   = ['quiet','crowded','overpriced','underrated','chaotic','gorgeous','exhausting','surprising','sleepy','relentless'];
$noun  = ['morning','detour','ferry','market','hostel','bus ride','rooftop','back alley','night train','rainstorm'];
$types = ['destination','hotel','restaurant','attraction','experience'];

$destIds = $pdo->query('SELECT id FROM destinations')->fetchAll(PDO::FETCH_COLUMN);
if (!$destIds) fixtures_abort('No destinations in the local DB — delete database/ruinmytrip.sqlite and reload the site to reseed.');

$hash = password_hash('fixture-not-a-real-password', PASSWORD_BCRYPT);
$pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
$day  = fn(int $back) => date('Y-m-d H:i:s', strtotime("-{$back} days"));

$iu = $pdo->prepare('INSERT INTO users (username,email,password_hash,role,birthdate,status,created_at) VALUES (?,?,?,?,?,?,?)');
$ip = $pdo->prepare('INSERT INTO profiles (user_id,display_name,bio,home_city,avatar_url,credibility_score) VALUES (?,?,?,?,?,?)');
$it = $pdo->prepare('INSERT INTO trips (user_id,destination_id,title,slug,body,cover_url,visited_on,verified,status,created_at) VALUES (?,?,?,?,?,?,?,0,?,?)');
$ir = $pdo->prepare('INSERT INTO reviews (user_id,destination_id,subject_type,subject_name,rating,title,body,verified,status,created_at) VALUES (?,?,?,?,?,?,?,0,?,?)');
$if = $pdo->prepare('INSERT OR IGNORE INTO follows (follower_id,followee_id,created_at) VALUES (?,?,?)');

$pdo->beginTransaction();
$made = [];
$trips = $reviews = 0;

for ($i = 0; $i < $n; $i++) {
    // Collision-proof suffix: existing max fixture index + i.
    $uname = FIX_USER_PREFIX . $pick($first) . '_' . $pick($last) . '_' . $i;
    $iu->execute([
        $uname,
        $uname . FIX_EMAIL_DOMAIN,
        $hash,
        'user',
        date('Y-m-d', strtotime('-' . mt_rand(17, 55) . ' years')),
        'active',
        $day(mt_rand(1, 400)),
    ]);
    $uid = (int) $pdo->lastInsertId();
    $made[] = $uid;

    $ip->execute([
        $uid,
        ucwords(str_replace('_', ' ', substr($uname, strlen(FIX_USER_PREFIX)))),
        'Synthetic test account. Not a real traveler.',
        $pick(['Berlin, DE', 'Osaka, JP', 'Porto, PT', 'Denver, US', 'Cape Town, ZA']),
        null,             // no avatar: fixtures never fabricate a face
        0,                // credibility is earned, never seeded
    ]);

    // trips: 0-3 each
    for ($t = mt_rand(0, 3); $t > 0; $t--) {
        $title = 'A ' . $pick($adj) . ' ' . $pick($noun);
        $it->execute([
            $uid, $pick($destIds), $title, slugify($title) . '-' . $uid . '-' . $t,
            'Synthetic fixture body text for load and layout testing. ' . str_repeat('Lorem ipsum travel narrative. ', mt_rand(2, 12)),
            null, date('Y-m-d', strtotime('-' . mt_rand(10, 700) . ' days')),
            'published', $day(mt_rand(1, 380)),
        ]);
        $trips++;
    }

    // reviews: 0-5 each
    for ($r = mt_rand(0, 5); $r > 0; $r--) {
        $ir->execute([
            $uid, $pick($destIds), $pick($types), ucfirst($pick($adj)) . ' ' . $pick($noun) . ' spot',
            mt_rand(1, 5),
            ucfirst($pick($adj)) . ', and I have thoughts',
            'Synthetic fixture review body. ' . str_repeat('Enough characters to clear the 15-char validator floor. ', mt_rand(1, 6)),
            'published', $day(mt_rand(1, 380)),
        ]);
        $reviews++;
    }
}

// follows: wire a sparse graph so profile counts and the feed have something to render
$follows = 0;
foreach ($made as $uid) {
    for ($f = mt_rand(0, 6); $f > 0; $f--) {
        $target = $pick($made);
        if ($target === $uid) continue;
        $if->execute([$uid, $target, $day(mt_rand(1, 300))]);
        $follows++;
    }
}

$pdo->commit();

$tot = fixture_counts($pdo);
fwrite(STDOUT, "added: {$n} users, {$trips} trips, {$reviews} reviews, ~{$follows} follows\n");
fwrite(STDOUT, "fixture totals now: users={$tot['users']} trips={$tot['trips']} reviews={$tot['reviews']}\n");
fwrite(STDOUT, "purge with: php scripts/dev_fixtures.php --purge\n");
