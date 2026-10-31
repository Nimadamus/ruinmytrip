<?php
/**
 * A site test account (+rmtqa) can run a live buddy test without reaching real travelers: its post
 * sends no match emails and stays out of the public buddy search.
 *
 *   php tests/qa_buddy_quiet_test.php
 */
putenv('DATABASE_URL');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$dest = q_one("SELECT id FROM destinations ORDER BY id LIMIT 1");
foreach (['qaquietpost' => 'qq+rmtqa9@example.invalid', 'realwatcher' => 'rw@example.invalid', 'realposter' => 'rp@example.invalid'] as $u => $e)
    q_run("INSERT INTO users (username,email,password_hash,role,status,created_at) VALUES (?,?,?,?,?,?)", [$u, $e, 'x', 'member', 'active', $now]);
$id = fn($u) => (int) q_one("SELECT id FROM users WHERE username=?", [$u])['id'];
q_run("INSERT INTO saves (user_id,target_type,target_id,created_at) VALUES (?,?,?,?)", [$id('realwatcher'), 'destination', (int) $dest['id'], $now]);
$from = date('Y-m-d', strtotime('+20 days')); $to = date('Y-m-d', strtotime('+25 days'));
$mk = function (string $u) use ($id, $dest, $from, $to): int {
    $v = rmt_buddy_validate(['trip_type' => 'trip', 'destination_id' => (string) $dest['id'], 'title' => 'Looking for company in town', 'where_text' => '',
         'date_from' => $from, 'date_to' => $to, 'spots' => '1', 'budget' => 'any',
         'description' => 'Would love someone to explore museums and cafes with.', 'safety_ack' => '1']);
    if (!$v['ok']) throw new RuntimeException(implode(' ', $v['errors']));
    return rmt_buddy_insert($id($u), $v['data']);
};
$qa = $mk('qaquietpost');
ok(rmt_is_qa_user($id('qaquietpost')) && !rmt_is_qa_user($id('realwatcher')), 'QA accounts are recognised by their +rmtqa address');
ok(rmt_buddy_notify_matches('buddy', $qa) === 0, 'a QA post notifies nobody');
ok(!q_one("SELECT 1 x FROM notifications WHERE user_id=? AND target_id=?", [$id('realwatcher'), $qa]), 'the city watcher got no notification');
$real = $mk('realposter');
ok(rmt_buddy_notify_matches('buddy', $real) > 0, 'a real post still notifies the city watcher');
$ids = array_map(fn($r) => (int) ($r['id'] ?? 0), array_filter(rmt_buddy_search_run(rmt_buddy_filters([]), null, 200), fn($c) => $c['kind'] === 'post'));
ok(!in_array($qa, $ids, true), 'the QA post is not in the public buddy search');
$pdo->rollBack();
echo $fails ? "\nqa_buddy_quiet_test: $fails FAILED\n" : "\nqa_buddy_quiet_test: all passed\n";
exit($fails ? 1 : 0);
