<?php
/**
 * Sailing pages (app/sailings.php): one ship and one departure day, the members on it, the signed out
 * alert with double opt in, and the alerts a new cruise post lands on.
 *
 *   php tests/sailings_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';
ini_set('display_errors', '0');

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }
function render(callable $fn, array $a = []): string { ob_start(); $fn($a); return (string) ob_get_clean(); }

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$sail = date('Y-m-d', strtotime('+40 days'));
foreach (['sailone', 'sailtwo'] as $u)
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,birthdate,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', $now, '1990-01-01', $now]);
$uid = fn($u) => (int) q_one('SELECT id FROM users WHERE username=?', [$u])['id'];
foreach (['sailone', 'sailtwo'] as $u) rmt_profile_ensure($uid($u));

// Slugs and names.
ok(rmt_sailing_ship_slug('Norwegian Encore') === 'norwegian-encore', 'a ship name becomes a slug');
ok(rmt_sailing_path('Norwegian Encore', $sail) === '/sailing/norwegian-encore/' . $sail, 'and a sailing path');
ok(rmt_sailing_ship_name('norwegian-encore') === 'Norwegian Encore', 'a slug reads back as a name');
ok(!rmt_sailing_valid_date('2026-02-30') && rmt_sailing_valid_date($sail), 'only real dates make a sailing');

// Alert validation.
$v = rmt_sailing_alert_validate(['email' => 'A@Example.com', 'ship' => 'Norwegian Encore', 'sail_date' => $sail]);
ok($v['ok'] && $v['data']['email'] === 'a@example.com' && $v['data']['ship_key'] === 'norwegianencore', 'a good alert is accepted and keyed');
ok(!rmt_sailing_alert_validate(['email' => 'nope', 'ship' => 'Norwegian Encore', 'sail_date' => $sail])['ok'], 'a bad email is refused');
ok(!rmt_sailing_alert_validate(['email' => 'a@example.com', 'ship' => 'x', 'sail_date' => $sail])['ok'], 'a ship is required');
ok(!rmt_sailing_alert_validate(['email' => 'a@example.com', 'ship' => 'Norwegian Encore', 'sail_date' => '2020-01-01'])['ok'], 'a sailing that left is refused');

// Saving: one alert per address and sailing, pending until confirmed.
$s1 = rmt_sailing_alert_save($v['data']);
$s2 = rmt_sailing_alert_save($v['data']);
ok($s1['id'] === $s2['id'], 'asking twice is one alert');
$a = rmt_sailing_alert_by_token($s2['token']);
ok($a && $a['status'] === 'pending' && (int) $a['id'] === $s1['id'], 'the newest link finds it, still pending');
ok(rmt_sailing_alert_by_token($s1['token']) === null, 'an older link no longer works');
ok(rmt_sailing_watchers('norwegianencore', $sail) === 0, 'a pending alert is not counted');

// A member posts the same ship, a day off: a confirmed alert is a target, a pending one is not.
q_run("INSERT INTO buddy_posts (user_id, trip_type, title, where_text, date_from, date_to, status, created_at, updated_at, cruise_line, ship, ship_key, description)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
      [$uid('sailone'), 'cruise', 'Norwegian Encore sailing', 'Norwegian Encore', date('Y-m-d', strtotime($sail . ' +1 day')),
       date('Y-m-d', strtotime($sail . ' +8 days')), 'open', $now, $now, 'Norwegian Cruise Line', 'Norwegian Encore', 'norwegianencore', 'First cruise, happy to meet for sail away.']);
$post = q_one("SELECT * FROM buddy_posts WHERE user_id=? ORDER BY id DESC LIMIT 1", [$uid('sailone')]);
ok(rmt_sailing_alert_targets($post) === [], 'a pending alert is never emailed');
q_run("UPDATE sailing_alerts SET status='active', confirmed_at=? WHERE id=?", [$now, $s1['id']]);
ok(count(rmt_sailing_alert_targets($post)) === 1, 'a confirmed alert on the same ship and day is told');
ok(rmt_sailing_watchers('norwegianencore', $sail) === 1, 'and counted as watching');
$other = $post; $other['ship_key'] = 'norwegianbliss';
ok(rmt_sailing_alert_targets($other) === [], 'another ship is not');
$far = $post; $far['date_from'] = date('Y-m-d', strtotime($sail . ' +5 days'));
ok(rmt_sailing_alert_targets($far) === [], 'nor the same ship on another week');
q_run("INSERT INTO sailing_alerts (email, ship_key, ship_name, sail_date, status, token_hash, created_at) VALUES (?,?,?,?,?,?,?)",
      ['sailone@example.invalid', 'norwegianencore', 'Norwegian Encore', $sail, 'active', hash('sha256', 'own'), $now]);
ok(count(rmt_sailing_alert_targets($post)) === 1, 'the poster is never emailed about their own post');

// Members on the sailing.
ok(count(rmt_sailing_members('norwegianencore', $sail)) === 1, 'the member is on the sailing (a day either side)');
q_run("UPDATE users SET email='sailone+rmtqa@example.invalid' WHERE id=?", [$uid('sailone')]);
ok(rmt_sailing_members('norwegianencore', $sail) === [], 'a site test account never shows');
q_run("UPDATE users SET email='sailone@example.invalid' WHERE id=?", [$uid('sailone')]);

// The page, signed out.
$h = render('sailing_show', ['ship' => 'norwegian-encore', 'date' => $sail]);
ok(str_contains($h, 'Norwegian Encore, leaving ') && str_contains($h, '1</b> traveler has posted this sailing'), 'the page names the sailing and counts the member');
ok(str_contains($h, 'action="' . e(url('sailing-alerts')) . '"') && str_contains($h, 'name="sail_date" value="' . $sail . '"'), 'a visitor gets the alert form for this sailing');
ok(str_contains($h, '<meta name="robots" content="index, follow"'), 'the page says index');
$e = render('sailing_show', ['ship' => 'norwegian-bliss', 'date' => $sail]);
ok(str_contains($e, 'You are early'), 'an empty sailing invites the first traveler instead of showing zero');

// The off switch is signed.
$al = rmt_sailing_alert_by_id($s1['id']);
ok(hash_equals(rmt_sailing_off_sig($s1['id'], $al['email']), rmt_sailing_off_sig($s1['id'], 'a@example.com'))
   && !hash_equals(rmt_sailing_off_sig($s1['id'], $al['email']), rmt_sailing_off_sig($s1['id'], 'b@example.com')), 'the off link is tied to the address');

$pdo->rollBack();
echo $fails ? "\nsailings_test: $fails FAILED\n" : "\nsailings_test: all passed\n";
exit($fails ? 1 : 0);
