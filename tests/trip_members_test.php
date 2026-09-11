<?php
/**
 * Collaborative trips: who may see a shared trip, and who may do what to it.
 *
 * A trip somebody invited you onto has to be visible to you whatever its visibility, or the
 * invitation means nothing. That is a hole cut in the privacy model on purpose, which makes it the
 * most dangerous thing in this session's work, so it is pinned down from both ends:
 *
 *   - the SQL clause every list uses, rmt_plan_visibility_sql()
 *   - the PHP twin, rmt_trip_visible_to()
 *
 * and the two are checked against each other on the same rows, because a hole that exists in one
 * and not the other is how a private trip ends up on a city page.
 *
 * It also pins the permission split, which is the other half of the design: an editor adds, an
 * owner destroys and publishes. A person invited to help plan a holiday must not be one misclick
 * from making it public, removing the owner, or deleting it.
 *
 *   php tests/trip_members_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/plans.php';
require BASE_PATH . '/app/trip_members.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $what): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  PASS  $what\n"; } else { $fail++; echo "FAIL: $what\n"; }
}

$pdo = db();
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT DEFAULT 'active')");
$pdo->exec("CREATE TABLE profiles (user_id INT, display_name TEXT, avatar_url TEXT)");
$pdo->exec("CREATE TABLE follows (followee_id INT, follower_id INT)");
$pdo->exec("CREATE TABLE blocks (blocker_id INT, blocked_id INT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)");
$pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT,
              actor_id INT, target_type TEXT, target_id INT, created_at TEXT, read_at TEXT)");
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INT, destination_id INT, title TEXT,
              slug TEXT, status TEXT DEFAULT 'published', visibility TEXT DEFAULT 'public',
              date_from TEXT, date_to TEXT)");
$pdo->exec("CREATE TABLE trip_members (trip_id INT, user_id INT, role TEXT, state TEXT,
              invited_by INT, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");

$pdo->exec("INSERT INTO users (id,username) VALUES (1,'ana'),(2,'ben'),(3,'cara'),(4,'dan')");
$pdo->exec("INSERT INTO destinations VALUES (1,'Lisbon','lisbon')");
/* Ana's three trips: one public, one for followers, one private. Ben is invited onto the private
   one, which is the case the whole feature turns on. Cara follows Ana. Dan is a stranger. */
$pdo->exec("INSERT INTO trips (id,user_id,destination_id,title,slug,visibility) VALUES
  (1,1,1,'Public trip','pub','public'),
  (2,1,1,'Followers trip','fol','followers'),
  (3,1,1,'Private trip','priv','private')");
$pdo->exec("INSERT INTO follows VALUES (1,3)");
$pdo->exec("INSERT INTO trip_members (trip_id,user_id,role,state,invited_by,created_at)
            VALUES (3,2,'editor','active',1,'2026-09-01 10:00:00')");

$ana = ['id' => 1, 'username' => 'ana'];
$ben = ['id' => 2, 'username' => 'ben'];
$cara = ['id' => 3, 'username' => 'cara'];
$dan = ['id' => 4, 'username' => 'dan'];

/** Trip ids a viewer can see through the SQL clause every list on the site uses. */
$visibleIds = static function (?array $viewer) use ($pdo): array {
    [$sql, $args] = rmt_plan_visibility_sql('t', $viewer);
    $st = $pdo->prepare("SELECT t.id FROM trips t WHERE t.status='published' AND $sql ORDER BY t.id");
    $st->execute($args);
    return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
};
$trip = static fn(int $id): array => q_one('SELECT * FROM trips WHERE id = ?', [$id]);

// --- who can see what ---------------------------------------------------------------------------
ok($visibleIds(null) === [1], 'a stranger who is not signed in sees only the public trip');
ok($visibleIds($dan) === [1], 'and so does a signed-in stranger');
ok($visibleIds($cara) === [1, 2], 'a follower sees the followers-only one too, and not the private one');
ok($visibleIds($ben) === [1, 3], 'somebody invited onto the private trip sees it, and gains nothing else');
ok($visibleIds($ana) === [1, 2, 3], 'and the traveler whose trips they are sees all three');

/* The PHP twin has to agree with the SQL on every row, for every viewer. A hole in one and not the
   other is exactly how a private trip reaches a page. */
$disagreements = [];
foreach ([null, $ana, $ben, $cara, $dan] as $v) {
    $sqlSet = $visibleIds($v);
    foreach ([1, 2, 3] as $id) {
        $php = rmt_trip_visible_to($trip($id), $v);
        if ($php !== in_array($id, $sqlSet, true)) {
            $disagreements[] = ($v['username'] ?? 'anon') . '/trip' . $id;
        }
    }
}
ok($disagreements === [], 'the SQL clause and the PHP check agree on every trip for every viewer'
   . ($disagreements ? ': ' . implode(', ', $disagreements) : ''));

// An invitation is not membership. Until it is answered, nothing changes.
$pdo->exec("INSERT INTO trip_members (trip_id,user_id,role,state,invited_by,created_at)
            VALUES (2,4,'editor','invited',1,'2026-09-01 10:00:00')");
ok($visibleIds($dan) === [1], 'being asked is not being on it: an unanswered invitation reveals nothing');

rmt_trip_invite_answer(2, 4, true);
ok($visibleIds($dan) === [1, 2], 'saying yes is what lets somebody in');
rmt_trip_member_remove($trip(2), $ana, 4);
ok($visibleIds($dan) === [1], 'and being removed takes it away again');

// --- who may do what ----------------------------------------------------------------------------
ok(rmt_trip_role($trip(3), $ana) === 'owner', 'the traveler whose trip it is, is the owner');
ok(rmt_trip_role($trip(3), $ben) === 'editor', 'the person invited is an editor');
ok(rmt_trip_role($trip(3), $dan) === null, 'and a stranger is nothing');
ok(rmt_trip_role($trip(3), null) === null, 'as is nobody at all');

ok(rmt_trip_can_edit($trip(3), $ben), 'an editor may add to the trip');
ok(!rmt_trip_can_admin($trip(3), $ben), 'and may never publish it, delete it, or change who is on it');
ok(rmt_trip_can_admin($trip(3), $ana), 'which only the owner may do');
ok(!rmt_trip_can_edit($trip(3), $dan), 'a stranger may not add to somebody else\'s trip');

// The owner is not removable, by anybody, including themselves: a trip with no owner has nobody
// who can delete it.
ok(rmt_trip_member_remove($trip(3), $ana, 1)['ok'] === false, 'the owner cannot be removed');
ok(rmt_trip_member_remove($trip(3), $ben, 1)['ok'] === false, 'not by an editor either');
ok(rmt_trip_member_remove($trip(3), $ben, 2)['ok'] === true, 'but anybody may leave a trip themselves');
ok(rmt_trip_role($trip(3), $ben) === null, 'and after leaving they are nothing again');
ok($visibleIds($ben) === [1], 'and the private trip goes back to being invisible to them');

// --- inviting -----------------------------------------------------------------------------------
ok(rmt_trip_invite($trip(3), $ben, 'cara')['ok'] === false, 'an editor cannot invite anybody');
ok(rmt_trip_invite($trip(3), $ana, 'ana')['ok'] === false, 'nobody invites themselves');
ok(rmt_trip_invite($trip(3), $ana, 'nobody_at_all')['ok'] === false, 'an unknown username is a no');

$pdo->exec('INSERT INTO blocks VALUES (3,1)');
ok(rmt_trip_invite($trip(3), $ana, 'cara')['ok'] === false, 'somebody who blocked you cannot be invited');
$pdo->exec('DELETE FROM blocks');
$pdo->exec('INSERT INTO blocks VALUES (1,3)');
ok(rmt_trip_invite($trip(3), $ana, 'cara')['ok'] === false, 'and neither can somebody you blocked');
$pdo->exec('DELETE FROM blocks');

$r = rmt_trip_invite($trip(3), $ana, '@cara');
ok($r['ok'] === true, 'an at sign in front of the username is fine');
ok(rmt_trip_invite($trip(3), $ana, 'cara')['ok'] === false, 'and asking twice is not');
ok((int) (q_one("SELECT COUNT(*) c FROM notifications WHERE user_id = 3 AND type = 'trip_invite'")['c'] ?? 0) === 1,
   'one invitation is one notification');

// Declining is remembered, and re-asking reuses the row rather than piling up.
rmt_trip_invite_answer(3, 3, false);
ok(rmt_trip_role($trip(3), $cara) === null, 'a no leaves them off the trip');
ok(rmt_trip_invite($trip(3), $ana, 'cara')['ok'] === true, 'asking once more after a no is allowed');
ok((int) (q_one('SELECT COUNT(*) c FROM trip_members WHERE trip_id = 3 AND user_id = 3')['c'] ?? 0) === 1,
   'and it reuses the one row rather than making a second');

// --- the cap ------------------------------------------------------------------------------------
ok(rmt_trip_member_count(3) === 2, 'the count includes the owner and the person still deciding');
for ($i = 10; $i < 10 + RMT_TRIP_MEMBER_MAX; $i++) {
    $pdo->exec("INSERT INTO users (id,username) VALUES ($i,'u$i')");
    $pdo->exec("INSERT INTO trip_members (trip_id,user_id,role,state,invited_by,created_at)
                VALUES (1,$i,'editor','active',1,'2026-09-01 10:00:00')");
}
ok(rmt_trip_invite($trip(1), $ana, 'ben')['ok'] === false, 'a full trip takes nobody else');

// --- an account that is gone ---------------------------------------------------------------------
$pdo->exec("UPDATE users SET status = 'deleted' WHERE id = 2");
$pdo->exec("INSERT INTO trip_members (trip_id,user_id,role,state,invited_by,created_at)
            VALUES (2,2,'editor','active',1,'2026-09-01 10:00:00')");
$names = array_column(rmt_trip_members(2), 'username');
ok(!in_array('ben', $names, true), 'a deleted account is not listed as planning anything');

echo "trip_members_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
