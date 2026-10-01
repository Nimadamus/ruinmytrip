<?php
/** The activation funnel (rmt_activation_funnel, 2026-10-01): distinct browsers per stage, selfcheck excluded, return = later day. */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
                      'db_driver' => 'sqlite', 'sqlite_path' => ':memory:'];
const RMT_EDITORIAL_ROLE = 'editorial';
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/growth_funnel.php';
$pass = 0; $fail = 0;
function ok(string $n, $g, $x): void { global $pass, $fail; if ($g === $x) { $pass++; echo "  [PASS] $n\n"; } else { $fail++; echo "  [FAIL] $n expected=" . var_export($x, true) . ' got=' . var_export($g, true) . "\n"; } }
db()->exec("CREATE TABLE contribution_events (id INTEGER PRIMARY KEY, event TEXT, visitor TEXT, acq_content TEXT, detail TEXT, created_at TEXT)");
$now = date('Y-m-d H:i:s'); $yday = date('Y-m-d H:i:s', time() - 86400);
foreach ([['landing_view','a',$yday],['landing_view','b',$now],['landing_view','c',$now],['landing_view','a',$now],
          ['cta_click','a',$yday],['join_view','a',$yday],['join_created','a',$yday],['trip_created','a',$yday],
          ['destination_follow_success','a',$yday],['question_posted','a',$now],['landing_view','x',$now]] as [$e,$v,$t]) {
    q_run('INSERT INTO contribution_events (event,visitor,created_at,acq_content) VALUES (?,?,?,?)', [$e, $v, $t, $v === 'x' ? 'selfcheck' : null]);
}
$f = array_column(rmt_activation_funnel(0), 'count', 'key');
ok('three browsers landed, selfcheck excluded', $f['landing'], 3);
ok('one clicked join', $f['join_click'], 1);
ok('one created an account', $f['reg_done'], 1);
ok('one added a trip', $f['trip'], 1);
ok('one followed', $f['follow'], 1);
ok('one contributed', $f['contribution'], 1);
ok('nobody interacted socially', $f['social'], 0);
ok('the member came back on a later day', $f['return'], 1);
printf("\n%s: %d passed, %d failed\n", basename(__FILE__, '.php'), $pass, $fail);
exit($fail ? 1 : 0);
