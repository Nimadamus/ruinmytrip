<?php
/**
 * Hosting paused (Nima, 2026-11-07): nothing new starts, nothing already arranged breaks.
 *   - a new stay request is refused with the pause note, even to an approved host
 *   - a stay that was already requested can still be accepted, confirmed and cancelled
 *   - the hub, city page and become-a-host page promote no hosting and say why
 *   - meeting a local is untouched
 *   php tests/hosting_pause_test.php
 */
putenv('DATABASE_URL');
putenv('RESEND_API_KEY');
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers.php';
ini_set('display_errors', '0');

$fails = 0;
function ok(bool $c, string $n): void { global $fails; if (!$c) $fails++; echo ($c ? 'PASS  ' : 'FAIL  ') . $n . "\n"; }
function render(callable $fn): string { ob_start(); try { $fn(); } finally { $h = (string) ob_get_clean(); } return $h; }

ok(rmt_hosting_paused(), 'hosting is paused');
ok(rmt_stays_enabled(), 'the stays feature itself is not switched off (existing stays keep working)');

$pdo = db(); $pdo->beginTransaction();
$now = date('Y-m-d H:i:s');
$dest = q_one("SELECT id, slug FROM destinations ORDER BY id LIMIT 1");
foreach (['phost' => '1985-01-01', 'pguest' => '1992-01-01'] as $u => $bd) {
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,birthdate,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', 'member', 'active', $now, $bd, $now]);
    rmt_profile_ensure((int) q_one('SELECT id FROM users WHERE username=?', [$u])['id']);
}
$user = fn($u) => q_one("SELECT * FROM users WHERE username=?", [$u]);
$id = fn($u) => (int) $user($u)['id'];
foreach (['phost', 'pguest'] as $u)
    q_run("UPDATE profiles SET display_name=?, bio='I travel slowly and like long walks and markets.' WHERE user_id=?", [ucfirst($u), $id($u)]);
q_run("INSERT INTO host_profiles (user_id,status,destination_id,neighborhood,space_type,max_guests,min_nights,max_nights,about_space,pets,smoking,created_at,updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", [$id('phost'), 'approved', $dest['id'], 'Centro', 'private_room', 2, 1, 4, 'A room.', 'none', 'no', $now, $now]);
$host = fn() => rmt_stay_host_by_username('phost');

// Nothing new starts.
ok(rmt_stay_blocked_reason($user('pguest'), $host()) === RMT_HOSTING_PAUSED_NOTE, 'a new stay request to an approved host is refused with the pause note');

// Nothing already arranged breaks: a request made before the pause moves through its whole life.
$d1 = date('Y-m-d', strtotime('+10 days')); $d3 = date('Y-m-d', strtotime('+13 days'));
q_run("INSERT INTO stay_requests (guest_id, host_id, date_from, date_to, guests, intro, state, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)",
      [$id('pguest'), $id('phost'), $d1, $d3, 1, 'Visiting for a conference before the pause.', 'requested', $now, $now]);
$sid = (int) q_one('SELECT id FROM stay_requests WHERE guest_id=? ORDER BY id DESC', [$id('pguest')])['id'];
$s = fn() => rmt_stay($sid);
ok(rmt_stay_move($s(), $user('phost'), 'accepted')['ok'], 'an existing request can still be accepted');
ok(rmt_message_allowed($id('pguest'), $id('phost'))['ok'], 'messages still open after a yes');
ok(rmt_stay_move($s(), $user('pguest'), 'confirmed')['ok'], 'an existing stay can still be confirmed');
ok(rmt_stay_move($s(), $user('pguest'), 'cancelled')['ok'], 'and still cancelled');

// Hosts are neither listed nor promoted; locals are.
$hub = render(fn() => hosts_locals_hub([]));
ok(str_contains($hub, 'New stays and new host applications are paused'), 'the hub says hosting is paused');
ok(!str_contains($hub, 'hosts-locals/become-a-host"'), 'the hub has no Become a host button');
ok(!str_contains($hub, 'stay/new/phost'), 'the hub lists no host to ask');
ok(str_contains($hub, 'Find locals'), 'meeting a local is still offered');
$city = render(fn() => hosts_locals_city(['slug' => $dest['slug']]));
ok(!str_contains($city, 'stay/new/phost'), 'a city page lists no host to ask');
$apply = render(fn() => hosts_locals_become_host([]));
ok(str_contains($apply, 'New host applications are paused') && !str_contains($apply, 'class="hl-form"'), 'become-a-host shows the pause, not the form');
$card = render(function () { $hc = rmt_hl_hosts([], null, 60)[0] ?? null; if ($hc) include dirname(__DIR__) . '/views/_hl_host.php'; });
ok($card === '' || (str_contains($card, 'New stays paused') && !str_contains($card, 'Ask to stay')), 'a host card, wherever it is drawn, offers no stay request');

$pdo->rollBack();
echo $fails ? "hosting_pause_test: $fails FAILED\n" : "hosting_pause_test: all passed\n";
exit($fails ? 1 : 0);
