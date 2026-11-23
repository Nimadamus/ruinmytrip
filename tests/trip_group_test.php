<?php
/**
 * Group trip link (app/trip_group.php, migration 132).
 *
 * What must hold: the link only opens a published trip with that exact code, and a reset kills the
 * old one; a guest joins with a cleaned first name and is recognised by their key alone; the group
 * is capped; only people on the trip can tick, claim or vote, and only on items the city really
 * has; a vote counts once; only the owner settles dates, and the trip takes them; the group tax
 * totals a flat per person rate and does not invent a total for a percentage.
 *
 *   php tests/trip_group_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt',
];
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/tourist_tax.php';
require BASE_PATH . '/app/city_scams.php';
require BASE_PATH . '/app/tax_study.php';
require BASE_PATH . '/app/before_you_book.php';
require BASE_PATH . '/app/trip_checklist.php';
require BASE_PATH . '/app/trip_members.php';
require BASE_PATH . '/app/trip_group.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; return; }
    $fail++;
    printf("  [FAIL] %-60s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
$pdo->exec("CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INTEGER, destination_id INTEGER, title TEXT, slug TEXT,
             status TEXT DEFAULT 'published', date_from TEXT, date_to TEXT, visibility TEXT DEFAULT 'private')");
$pdo->exec("CREATE TABLE trip_members (trip_id INTEGER, user_id INTEGER, role TEXT DEFAULT 'editor', state TEXT DEFAULT 'invited',
             invited_by INTEGER, created_at TEXT, decided_at TEXT, PRIMARY KEY (trip_id, user_id))");
$pdo->exec('CREATE TABLE trip_checklist (trip_id INTEGER NOT NULL, item_key TEXT NOT NULL, user_id INTEGER, done_at TEXT NOT NULL, PRIMARY KEY (trip_id, item_key))');
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/132_trip_groups.sqlite.sql'));
$pdo->exec("INSERT INTO users (id, username) VALUES (1, 'nima'), (2, 'sam'), (3, 'stranger')");
$pdo->exec("INSERT INTO destinations (id, name, slug) VALUES (1, 'Amsterdam', 'amsterdam-netherlands'), (2, 'Rome', 'rome-italy')");
$pdo->exec("INSERT INTO trips (id, user_id, destination_id, title, slug, date_from, date_to) VALUES
            (1, 1, 1, 'Amsterdam with the boys', 'ams', '2027-05-01', '2027-05-05'),
            (2, 1, 2, 'Rome', 'rome', '2027-06-01', '2027-06-04'),
            (3, 1, 1, 'Draft', 'draft', NULL, NULL)");
$pdo->exec("UPDATE trips SET status = 'draft' WHERE id = 3");
$pdo->exec("INSERT INTO trip_members (trip_id, user_id, state) VALUES (1, 2, 'active')");

echo "Links:\n";
$trip = q_one('SELECT * FROM trips WHERE id = 1');
$code = rmt_tg_code($trip);
ok('a code is 20 hex characters', (bool) preg_match('/^[a-f0-9]{20}$/', $code), true);
$trip = q_one('SELECT * FROM trips WHERE id = 1');
ok('asking again gives the same code', rmt_tg_code($trip), $code);
ok('the code opens the trip', (int) rmt_tg_trip($code)['id'], 1);
ok('...with its city', (string) rmt_tg_trip($code)['dest_slug'], 'amsterdam-netherlands');
ok('a wrong code opens nothing', rmt_tg_trip(str_repeat('a', 20)), null);
ok('a malformed code opens nothing', rmt_tg_trip("x' OR 1=1"), null);
$new = rmt_tg_code($trip, true);
ok('a reset makes a new code', $new !== $code, true);
ok('...and the old one stops working', rmt_tg_trip($code), null);
$code = $new;
$draft = q_one('SELECT * FROM trips WHERE id = 3');
ok('an unpublished trip never opens by link', rmt_tg_trip(rmt_tg_code($draft)), null);
$t = rmt_tg_trip($code);

echo "Guests:\n";
ok('a name is cleaned', rmt_tg_clean_name('  <b>Sam</b>  O\'Neil!! '), "Sam O'Neil");
ok('a name is capped', mb_strlen(rmt_tg_clean_name(str_repeat('a', 90))), 40);
ok('an empty name is refused', rmt_tg_join($t, '<>')['ok'], false);
$j = rmt_tg_join($t, 'Ali');
ok('a guest joins with a first name', $j['ok'], true);
$g = rmt_tg_guest(1, $j['key']);
ok('the guest is recognised by their key', (string) $g['name'], 'Ali');
ok('the key is not stored, only its hash', q_one('SELECT COUNT(*) n FROM trip_guests WHERE key_hash = ?', [$j['key']])['n'] == 0, true);
ok('a key for another trip is not this trip', rmt_tg_guest(2, $j['key']), null);
ok('a junk key is nobody', rmt_tg_guest(1, 'nope'), null);
$v = rmt_tg_viewer($t, null, $g);
ok('a guest is a guest', $v['kind'], 'guest');
ok('a guest votes as g:id', $v['voter'], 'g:' . (int) $g['id']);
ok('the owner is the owner', rmt_tg_viewer($t, ['id' => 1, 'username' => 'nima'], null)['kind'], 'owner');
ok('an active member is a member', rmt_tg_viewer($t, ['id' => 2, 'username' => 'sam'], null)['kind'], 'member');
ok('a signed in stranger is nobody yet', rmt_tg_viewer($t, ['id' => 3, 'username' => 'stranger'], null)['kind'], null);
ok('people: owner, member, guest', array_column(rmt_tg_people($t), 'name'), ['nima', 'sam', 'Ali']);
for ($i = 0; $i < RMT_TG_MAX_GUESTS; $i++) rmt_tg_join($t, 'G' . $i);
ok('the group is capped', rmt_tg_join($t, 'One more')['ok'], false);

echo "Checklist:\n";
$nobody = ['kind' => null, 'name' => '', 'voter' => ''];
ok('somebody not on the trip cannot tick', rmt_tg_checklist_act($t, 'tax', 'done', $nobody, null), false);
ok('a guest can tick a real item', rmt_tg_checklist_act($t, 'tax', 'done', $v, null), true);
ok('...and it is ticked', isset(rmt_trip_checklist_done(1)['tax']), true);
ok('ticking twice is one row', rmt_tg_checklist_act($t, 'tax', 'done', $v, null) && (int) q_one('SELECT COUNT(*) n FROM trip_checklist WHERE trip_id = 1')['n'] === 1, true);
ok('an item the city does not have is refused', rmt_tg_checklist_act($t, 'made-up', 'done', $v, null), false);
ok('an unknown action is refused', rmt_tg_checklist_act($t, 'tax', 'drop table', $v, null), false);
rmt_tg_checklist_act($t, 'tax2027', 'claim', $v, null);
ok('a claim names who is sorting it', rmt_tg_claims(1)['tax2027'] ?? '', 'Ali');
rmt_tg_checklist_act($t, 'tax2027', 'unclaim', ['kind' => 'member', 'name' => 'sam', 'voter' => 'u:2'], 2);
ok('somebody else cannot drop your claim', rmt_tg_claims(1)['tax2027'] ?? '', 'Ali');
rmt_tg_checklist_act($t, 'tax2027', 'unclaim', $v, null);
ok('you can drop your own', rmt_tg_claims(1), []);

echo "Dates:\n";
ok('dates in the past are refused', rmt_tg_add_option($t, '2020-01-01', '2020-01-03') !== '', true);
ok('the end before the start is refused', rmt_tg_add_option($t, '2030-05-05', '2030-05-01') !== '', true);
ok('a non date is refused', rmt_tg_add_option($t, '2030-02-30', '2030-03-02') !== '', true);
ok('a good option is added', rmt_tg_add_option($t, '2030-05-01', '2030-05-05'), '');
ok('the same option twice is refused', rmt_tg_add_option($t, '2030-05-01', '2030-05-05') !== '', true);
rmt_tg_add_option($t, '2030-06-01', '2030-06-05');
$opts = rmt_tg_options(1, $v['voter']);
ok('two options', count($opts), 2);
ok('a guest votes', rmt_tg_vote($t, (int) $opts[0]['id'], $v['voter'], true), true);
rmt_tg_vote($t, (int) $opts[0]['id'], $v['voter'], true);
ok('a vote counts once', rmt_tg_options(1, $v['voter'])[0]['votes'], 1);
ok('the viewer sees their own vote', rmt_tg_options(1, $v['voter'])[0]['mine'], true);
ok('nobody cannot vote', rmt_tg_vote($t, (int) $opts[0]['id'], '', true), false);
ok('an option from another trip cannot be voted', rmt_tg_vote(rmt_tg_trip(rmt_tg_code(q_one('SELECT * FROM trips WHERE id = 2'))), (int) $opts[0]['id'], 'g:1', true), false);
rmt_tg_vote($t, (int) $opts[0]['id'], $v['voter'], false);
ok('a vote can be taken back', rmt_tg_options(1, $v['voter'])[0]['votes'], 0);
ok('the owner settles the dates', rmt_tg_pick($t, (int) $opts[1]['id']), true);
ok('...the trip takes them', (string) q_one('SELECT date_from FROM trips WHERE id = 1')['date_from'], '2030-06-01');
ok('...and the vote closes', rmt_tg_options(1), []);

echo "Group tax:\n";
$rome = rmt_tg_trip(rmt_tg_code(q_one('SELECT * FROM trips WHERE id = 2')));
$rx = rmt_tg_tax($rome, 4);
ok('Rome has a sourced tax', $rx !== null, true);
ok('three nights from the dates', $rx['nights'], 3);
$ams = rmt_tg_tax(rmt_tg_trip($code), 4);
ok('Amsterdam is a percentage, so no invented total', $ams['total'], null);
ok('...but the rate is shown', str_contains($ams['rate'], '%'), true);
$none = rmt_tg_tax(['dest_slug' => 'nowhere-at-all', 'date_from' => null, 'date_to' => null], 3);
ok('a city with no tax data says nothing', $none, null);
$flat = null;
foreach (rmt_tt_cities() as $k => $c) {
    $tiers = array_values($c['tiers']);
    if (count($tiers) === 1 && isset($tiers[0]['pp']) && !isset($tiers[0]['pct']) && !isset($tiers[0]['bands']) && !isset($tiers[0]['room']) && !isset($c['cap'])) { $flat = [$k, $c]; break; }
}
if ($flat) {
    $fx = rmt_tg_tax(['dest_slug' => (string) $flat[1]['dest'], 'date_from' => '2030-01-01', 'date_to' => '2030-01-04'], 2);
    ok('a flat per person rate is totalled: ' . $flat[0], $fx['total'], rmt_tt_money(round((float) $flat[1]['tiers'][array_key_first($flat[1]['tiers'])]['pp'] * 2 * 3, 2), (string) $flat[1]['cur']));
}

echo $fail ? "\n$fail FAIL(S), $pass pass\n" : "\nALL PASS ($pass)\n";
exit($fail ? 1 : 0);
