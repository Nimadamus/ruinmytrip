<?php
/**
 * Safety signals: mass requests, a new account rushing, declines, reports and blocks get flagged; quiet members do not.
 *
 *   php tests/safety_signals_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';
ini_set('display_errors', '0');

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s'); $old = date('Y-m-d H:i:s', strtotime('-60 days'));
$mk = function (string $u, string $at) { q_run("INSERT INTO users (username,email,password_hash,role,status,created_at) VALUES (?,?,?,?,?,?)", [$u, $u . '@example.invalid', 'x', 'member', 'active', $at]);
    return (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id']; };
$spam = $mk('sgspam', $old); $rush = $mk('sgrush', $now); $quiet = $mk('sgquiet', $old); $bad = $mk('sgbad', $old);
$targets = []; for ($i = 0; $i < 12; $i++) $targets[] = $mk('sgt' . $i, $old);
foreach ($targets as $i => $t) q_run("INSERT INTO local_connects (from_user_id,to_user_id,state,created_at) VALUES (?,?,?,?)", [$spam, $t, $i < 6 ? 'declined' : 'interested', $now]);
foreach (array_slice($targets, 0, 5) as $t) q_run("INSERT INTO local_connects (from_user_id,to_user_id,state,created_at) VALUES (?,?,?,?)", [$rush, $t, 'interested', $now]);
foreach (array_slice($targets, 0, 2) as $t) q_run("INSERT INTO local_connects (from_user_id,to_user_id,state,created_at) VALUES (?,?,?,?)", [$quiet, $t, 'interested', $now]);
foreach (array_slice($targets, 0, 2) as $t) q_run("INSERT INTO reports (reporter_id,target_type,target_id,reason,status,created_at) VALUES (?,?,?,?,?,?)", [$t, 'user', $bad, 'harassment', 'open', $now]);
foreach (array_slice($targets, 0, 3) as $t) q_run("INSERT INTO blocks (blocker_id,blocked_id) VALUES (?,?)", [$t, $bad]);
// Old requests outside the window do not count.
foreach (array_slice($targets, 0, 12) as $t) q_run("INSERT INTO trip_connects (trip_id,from_user_id,to_user_id,state,created_at) VALUES (?,?,?,?,?)", [900000 + $t, $quiet, $t, 'interested', $old]);

$f = []; foreach (rmt_signal_flagged() as $r) $f[$r['username']] = $r;
ok(isset($f['sgspam']['flags']['requests']) && isset($f['sgspam']['flags']['declined']), 'mass requests and declines are flagged');
ok(isset($f['sgrush']['flags']['new_rush']) && !isset($f['sgrush']['flags']['requests']), 'a new account sending 5 is flagged as a rush');
ok(!isset($f['sgquiet']), 'a quiet member with old activity is not flagged');
ok(isset($f['sgbad']['flags']['reports']) && isset($f['sgbad']['flags']['blocked']), 'reports and blocks are flagged');
ok(!isset($f['sgt0']), 'the people on the receiving end are not flagged');
$keys = array_keys($f); ok(array_search('sgspam', $keys) < array_search('sgrush', $keys), 'more flags sort first');

$pdo->rollBack();
echo $fails ? "\nsafety_signals_test: $fails FAILED\n" : "\nsafety_signals_test: all passed\n";
exit($fails ? 1 : 0);
