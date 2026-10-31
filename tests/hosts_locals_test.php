<?php
/**
 * Hosts & Locals: stays stay off, host profiles are hidden until approved and never hold an
 * address, locals are found by city and filters, blocks hold, references need a real connection,
 * and every page renders for the people who should see it.
 *
 *   php tests/hosts_locals_test.php
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
$old = date('Y-m-d H:i:s', time() - 3 * 86400);
$dest = q_one("SELECT id, slug, name FROM destinations ORDER BY id LIMIT 1");
foreach (['hllocal', 'hlhost', 'hltraveler', 'hlstranger', 'hladmin'] as $u)
    q_run("INSERT INTO users (username,email,password_hash,role,status,email_verified_at,birthdate,created_at) VALUES (?,?,?,?,?,?,?,?)",
          [$u, $u . '@example.invalid', 'x', $u === 'hladmin' ? 'admin' : 'member', 'active', $now, '1990-01-01', $old]);
$user = fn($u) => q_one("SELECT * FROM users WHERE username=?", [$u]);
$id = fn($u) => (int) $user($u)['id'];
foreach (['hllocal', 'hlhost', 'hltraveler', 'hlstranger', 'hladmin'] as $u) rmt_profile_ensure($id($u));

ok(rmt_stays_enabled() === false, 'overnight stays are switched off');
putenv('MAIL_POSTAL_ADDRESS');
ok(rmt_welcome_email($id('hllocal')) === false, 'no welcome email while promotional mail is not compliant');

// Locals.
$v = rmt_local_validate(['destination_id' => $dest['id'], 'offers' => ['coffee', 'food', 'bogus'], 'availability' => 'Weekends']);
ok($v['ok'] && $v['data']['offers'] === ['coffee', 'food'], 'a local picks real offers only');
ok(!rmt_local_validate(['destination_id' => $dest['id'], 'offers' => []])['ok'], 'a local must offer something');
q_run("UPDATE profiles SET home_destination_id=?, open_to_meeting=1, local_offers='coffee,food', languages='en,pt' WHERE user_id=?", [$dest['id'], $id('hllocal')]);
$ids = fn(array $rows) => array_map(fn($r) => (int) $r['user_id'], $rows);
ok(in_array($id('hllocal'), $ids(rmt_hl_locals(['dest_id' => $dest['id']], $id('hltraveler'))), true), 'the local is found in their city');
ok(in_array($id('hllocal'), $ids(rmt_hl_locals(['dest_id' => $dest['id'], 'lang' => 'pt', 'offer' => 'food'], null)), true), 'and by language and offer');
ok(!in_array($id('hllocal'), $ids(rmt_hl_locals(['dest_id' => $dest['id'], 'offer' => 'transport'], null)), true), 'but not by an offer they did not make');
ok(!in_array($id('hllocal'), $ids(rmt_hl_locals(['dest_id' => $dest['id']], $id('hllocal'))), true), 'nobody sees themselves');
q_run("INSERT INTO blocks (blocker_id, blocked_id) VALUES (?,?)", [$id('hllocal'), $id('hlstranger')]);
ok(!in_array($id('hllocal'), $ids(rmt_hl_locals(['dest_id' => $dest['id']], $id('hlstranger'))), true), 'a block hides the local');
$roles = rmt_member_roles($id('hllocal'));
ok($roles['traveler'] && $roles['local'] && !$roles['host'], 'roles: traveler and local');

// Hosts.
$good = ['destination_id' => $dest['id'], 'neighborhood' => 'Old Town', 'space_type' => 'private_room', 'max_guests' => 2,
         'min_nights' => 1, 'max_nights' => 4, 'about_space' => 'A quiet private room with a desk, five minutes from the tram.',
         'pets' => 'none', 'smoking' => 'no', 'agree' => '1'];
ok(rmt_host_validate($good)['ok'], 'a complete host application is valid');
foreach (['12 Main Street', 'Rua Augusta', 'Apt 4, Chiado', 'Flat near park', 'Postcode 10115'] as $bad)
    ok(!rmt_host_validate(['neighborhood' => $bad] + $good)['ok'], 'an address is refused: ' . $bad);
ok(!rmt_host_validate(['max_nights' => 0] + $good)['ok'], 'stay lengths are checked');
ok(!rmt_host_validate(['agree' => ''] + $good)['ok'], 'the guidelines box is required');
$cols = array_column(q_all('PRAGMA table_info(host_profiles)'), 'name');
ok(!array_filter($cols, fn($c) => preg_match('/address|street|postal|lat|lng/', $c)), 'host_profiles has no address column');
ok(rmt_host_save($id('hlhost'), rmt_host_validate($good)['data']) === 'pending', 'a new application is pending');
ok(!in_array($id('hlhost'), $ids(rmt_hl_hosts(['dest_id' => $dest['id']], null)), true), 'a pending host is not shown');
q_run("UPDATE host_profiles SET status='approved' WHERE user_id=?", [$id('hlhost')]);
ok(in_array($id('hlhost'), $ids(rmt_hl_hosts(['dest_id' => $dest['id']], null)), true), 'an approved host is shown');
ok(rmt_host_save($id('hlhost'), rmt_host_validate(['about_space' => 'A changed description of the room that is long enough.'] + $good)['data']) === 'pending',
   'editing an approved profile sends it back to review');
q_run("UPDATE host_profiles SET status='paused' WHERE user_id=?", [$id('hlhost')]);
ok(!in_array($id('hlhost'), $ids(rmt_hl_hosts([], null)), true), 'a paused host is hidden');
q_run("UPDATE host_profiles SET status='approved' WHERE user_id=?", [$id('hlhost')]);
$c = rmt_hl_city_counts((int) $dest['id']);
ok($c['locals'] >= 1 && $c['hosts'] >= 1, 'city counts are live');

// References.
ok(rmt_ref_basis($id('hltraveler'), $id('hllocal')) === null, 'no connection, no reference');
ok(rmt_ref_blocked_reason($user('hltraveler'), $user('hltraveler')) !== null, 'nobody references themselves');
q_run("INSERT INTO local_connects (from_user_id,to_user_id,state,created_at,decided_at) VALUES (?,?,?,?,?)",
      [$id('hltraveler'), $id('hllocal'), 'accepted', $now, $now]);
ok(rmt_ref_basis($id('hltraveler'), $id('hllocal')) === null, 'a connection made today is not enough yet');
q_run("UPDATE local_connects SET decided_at=? WHERE from_user_id=?", [$old, $id('hltraveler')]);
$b = rmt_ref_basis($id('hllocal'), $id('hltraveler'));
ok($b && $b['kind'] === 'met', 'an accepted local connection a day old allows a reference, both ways');
ok(!rmt_ref_validate(['rating_overall' => 0, 'body' => 'short'])['ok'], 'a rating and real text are required');
$d = rmt_ref_validate(['rating_overall' => '5', 'rating_reliability' => '4', 'body' => 'Showed me the best tascas in town and was on time for everything.'])['data'];
$r = rmt_ref_save($user('hltraveler'), $user('hllocal'), $d);
ok($r['ok'] && $r['created'], 'the traveler writes a reference');
$r2 = rmt_ref_save($user('hltraveler'), $user('hllocal'), ['body' => 'Updated: still the best guide to the tascas of the old town.'] + $d);
ok($r2['ok'] && !$r2['created'], 'writing again updates the same reference');
ok((int) q_one('SELECT COUNT(*) n FROM member_references WHERE to_user_id=?', [$id('hllocal')])['n'] === 1, 'one reference per person');
ok(count(rmt_refs_for($id('hllocal'), null)) === 1, 'the reference is published');
ok(!rmt_ref_save($user('hlstranger'), $user('hllocal'), $d)['ok'], 'a stranger cannot write one');
q_run("UPDATE member_references SET status='hidden' WHERE to_user_id=?", [$id('hllocal')]);
ok(count(rmt_refs_for($id('hllocal'), null)) === 0, 'a hidden reference is not shown');
q_run("UPDATE member_references SET status='published' WHERE to_user_id=?", [$id('hllocal')]);
ok(isset(RMT_REPORT_TARGETS['reference']), 'a reference can be reported or disputed');

// Pages.
$_SESSION = []; $_GET = [];
$h = render('hosts_locals_hub');
ok(str_contains($h, 'Experience the world through the people who live there') && str_contains($h, 'Stay With a Local'), 'the hub renders for a visitor');
ok(str_contains($h, 'does not guarantee') || str_contains($h, 'cannot promise'), 'the hub says no check is a guarantee');
$city = render('hosts_locals_city', ['slug' => $dest['slug']]);
ok(str_contains($city, 'Locals in ' . $dest['name']) && str_contains($city, 'Stay requests not open yet'), 'the city page shows locals and a disabled stay button');
ok(!str_contains($city, 'Old Town Street'), 'no address on the city page');
ok(str_contains($city, 'content="index, follow"'), 'a city with members is indexable');
$empty = null;
foreach (q_all('SELECT id, slug FROM destinations ORDER BY id DESC LIMIT 40') as $cand) {
    $cc = rmt_hl_city_counts((int) $cand['id']);
    if ($cc['locals'] + $cc['hosts'] + $cc['going'] === 0) { $empty = $cand; break; }
}
if ($empty) ok(str_contains(render('hosts_locals_city', ['slug' => $empty['slug']]), 'content="noindex, follow"'), 'an empty city is noindex');
ok(str_contains(render('hosts_locals_page', ['page' => 'safety']), 'Nothing on this site guarantees personal safety'), 'the safety page renders');
ok(str_contains(render('hosts_locals_page', ['page' => 'how-it-works']), 'One profile, three roles'), 'how it works renders');
ok(str_contains(render('hosts_locals_destinations'), 'Hosts &amp; Locals by city'), 'the cities page renders');
ok(str_contains(render('hosts_locals_become_host'), 'Create your profile to apply'), 'become a host asks a visitor to join');
/* Signed-in pages are covered by scripts/ci/hosts_locals_e2e.py: current_user() caches the first
   answer for the whole process, so one CLI test cannot sign in as several people. */
$_SESSION = [];

$pdo->rollBack();
echo $fails ? "\nhosts_locals_test: $fails FAILED\n" : "\nhosts_locals_test: all passed\n";
exit($fails ? 1 : 0);
