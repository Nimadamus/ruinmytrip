<?php
/**
 * Before you go updates (app/before_you_go.php, migration 126).
 *
 * What must hold: a bad address, topic or city is refused; signing up twice is one row; nothing is
 * sent on the first sight of a city, only when its tax or warnings change; the city a person signed
 * up for is the only one they hear about (or every city, when they asked for that); unconfirmed and
 * switched off signups hear nothing; the off link is signed.
 *
 *   php tests/before_you_go_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt',
];
putenv('RESEND_API_KEY');
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/mail.php';

/* Stand-ins for the two data sources, so a "change" can be made in the test. */
$GLOBALS['tt'] = ['amsterdam' => ['name' => 'Amsterdam', 'rate' => '12.5%', 'tiers' => ['' => ['pct' => 12.5]], 'note' => 'n', 'dest' => 'amsterdam-netherlands'],
                  'rome' => ['name' => 'Rome', 'rate' => '6 euros', 'tiers' => ['' => ['pp' => 6.0]], 'note' => 'n', 'dest' => 'rome-italy']];
$GLOBALS['sc'] = ['amsterdam-netherlands' => ['items' => [['name' => 'Fake police', 'what' => 'x', 'avoid' => 'y']], 'checked' => '']];
function rmt_tt_cities(): array { return $GLOBALS['tt']; }
function rmt_city_scams_all(): array { return $GLOBALS['sc']; }
require BASE_PATH . '/app/before_you_go.php';

$pass = 0; $fail = 0;
function ok(string $name, $got, $expect): void {
    global $pass, $fail;
    if ($got === $expect) { $pass++; printf("  [PASS] %s\n", $name); return; }
    $fail++;
    printf("  [FAIL] %-56s expected=%s got=%s\n", $name, var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)");
$pdo->exec("INSERT INTO destinations (id, slug, name) VALUES (1, 'amsterdam-netherlands', 'Amsterdam')");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/126_before_you_go.sqlite.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/126_before_you_go.sqlite.sql'));
ok('the migration is safe to run twice', true, true);
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/131_byg_trip_dates.sqlite.sql'));

echo "\nValidation:\n";
$v = rmt_byg_validate(['email' => ' Me@Example.com ', 'topic' => 'tax', 'city' => 'amsterdam', 'source' => 'tax_calc']);
ok('a good signup passes', $v['ok'], true);
ok('the address is trimmed and lowercased', $v['data']['email'], 'me@example.com');
ok('the city name comes from our data, not the form', $v['data']['city_name'], 'Amsterdam');
ok('a bad address is refused', rmt_byg_validate(['email' => 'nope', 'topic' => 'tax', 'city' => ''])['ok'], false);
ok('an unknown topic is refused', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'news', 'city' => ''])['ok'], false);
ok('an unknown city is refused', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'tax', 'city' => 'atlantis'])['ok'], false);
ok('a scam city name comes from the destination row', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'scams', 'city' => 'amsterdam-netherlands'])['data']['city_name'], 'Amsterdam');
ok('every city is allowed', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'tax', 'city' => ''])['ok'], true);
ok('an unknown source is dropped', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'tax', 'city' => '', 'source' => 'evil'])['data']['source'], '');

echo "\nSaving and confirming:\n";
$a = rmt_byg_save($v['data']);
$b = rmt_byg_save($v['data']);
ok('signing up twice is one row', $a['id'], $b['id']);
ok('the second signup rotates the token', rmt_byg_by_token($a['token']), null);
ok('the new token finds the row', (int) (rmt_byg_by_token($b['token'])['id'] ?? 0), $a['id']);
ok('a malformed token finds nothing', rmt_byg_by_token("x' OR 1=1"), null);
$s = rmt_byg_by_id($a['id']);
ok('a new signup waits for the confirm click', $s['status'], 'pending');
ok('the label says what and where', rmt_byg_label($s), 'Tourist tax in Amsterdam');
ok('the page is the calculator for that city', rmt_byg_page($s), url('tourist-tax-calculator?city=amsterdam'));
$sig = rmt_byg_off_sig($a['id'], 'me@example.com');
ok('the off link carries the signature', str_contains(rmt_byg_off_url($s), 's=' . $sig), true);
ok('a signature for another address does not match', hash_equals($sig, rmt_byg_off_sig($a['id'], 'x@example.com')), false);

echo "\nChange detection:\n";
$now = date('Y-m-d H:i:s');
$c = rmt_byg_save(rmt_byg_validate(['email' => 'all@example.com', 'topic' => 'tax', 'city' => ''])['data']);
$d = rmt_byg_save(rmt_byg_validate(['email' => 'rome@example.com', 'topic' => 'tax', 'city' => 'rome'])['data']);
$e = rmt_byg_save(rmt_byg_validate(['email' => 'off@example.com', 'topic' => 'tax', 'city' => 'amsterdam'])['data']);
q_run("UPDATE byg_signups SET status = 'active', confirmed_at = ? WHERE id IN (?, ?, ?)", [$now, $a['id'], $c['id'], $d['id']]);
q_run("UPDATE byg_signups SET status = 'off' WHERE id = ?", [$e['id']]);

$dry = rmt_byg_notify(false);
ok('a dry run writes nothing', (int) q_one('SELECT COUNT(*) n FROM byg_state')['n'], 0);
$r = rmt_byg_notify(true);
ok('the first look only records every city', $r['changed'], []);
ok('...for tax and scams', (int) q_one('SELECT COUNT(*) n FROM byg_state')['n'], 3);
ok('a second look with nothing changed finds nothing', rmt_byg_notify(true)['changed'], []);

$GLOBALS['tt']['amsterdam']['tiers'] = ['' => ['pct' => 12.0]];
$GLOBALS['tt']['amsterdam']['name'] = 'Amsterdam'; // the name is not content
$r = rmt_byg_notify(true);
ok('a rate change is seen for that city only', $r['changed'], ['tax' => ['amsterdam']]);
ok('with mail off, nothing counts as sent', $r['sent'], 0);
ok('...and nobody is marked as emailed', (int) q_one('SELECT COUNT(*) n FROM byg_signups WHERE notified_at IS NOT NULL')['n'], 0);
ok('the change is recorded, so it is not seen twice', rmt_byg_notify(true)['changed'], []);

/* Who would be written to: the same query rmt_byg_notify() uses. */
$who = array_column(q_all("SELECT email FROM byg_signups WHERE status = 'active' AND topic = 'tax' AND (city = '' OR city IN ('amsterdam')) ORDER BY email"), 'email');
ok('the Amsterdam and every-city signups hear, Rome, pending and off do not', $who, ['all@example.com', 'me@example.com']);

$GLOBALS['sc']['amsterdam-netherlands']['items'][] = ['name' => 'Bike theft', 'what' => 'z', 'avoid' => ''];
ok('a new official warning is a change', rmt_byg_notify(true)['changed'], ['scams' => ['amsterdam-netherlands']]);

/* One sign up ask (rmt_cta_focus, 2026-11-21). */
ok('the Watch card is on by default', rmt_cta_focus(), true);
putenv('RMT_CTA_OLD=1');
ok('RMT_CTA_OLD=1 brings the old blocks back', rmt_cta_focus(), false);
putenv('RMT_CTA_OLD');
ok('ticket notes are a known source', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'scams', 'city' => 'amsterdam-netherlands', 'source' => 'ticket_note'])['data']['source'], 'ticket_note');
ok('no Google, no Google watch link', rmt_byg_watch_url('tax', 'amsterdam', 'tax_note'), null);

/* Trip dates, the page a signup came from, and reminders (2026-11-23, migration 131). */
echo "\nTrip dates and reminders:\n";
$in30 = date('Y-m-d', strtotime('+30 days'));
$tv = rmt_byg_validate(['email' => 'trip@example.com', 'topic' => 'tax', 'city' => 'rome', 'travel_date' => $in30, 'page' => '/blog/rome-tax-2026#byg']);
ok('a future trip date passes', $tv['ok'], true);
ok('the page keeps its path, not its fragment', $tv['data']['page'], '/blog/rome-tax-2026');
ok('a past trip date is refused', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'tax', 'city' => 'rome', 'travel_date' => date('Y-m-d', strtotime('-2 days'))])['ok'], false);
ok('a date three years out is refused', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'tax', 'city' => 'rome', 'travel_date' => date('Y-m-d', strtotime('+3 years'))])['ok'], false);
ok('a non date is refused', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'tax', 'city' => 'rome', 'travel_date' => '2027-02-30'])['ok'], false);
ok('no date is fine', rmt_byg_validate(['email' => 'a@b.co', 'topic' => 'tax', 'city' => 'rome', 'travel_date' => ''])['ok'], true);
ok('an outside page is dropped', rmt_byg_clean_page('https://evil.example/x'), '');
ok('a path outside the answer pages is dropped', rmt_byg_clean_page('/admin/users'), '');
ok('the calculator is an allowed page', rmt_byg_clean_page('/tourist-tax-calculator?city=rome'), '/tourist-tax-calculator');
$t1 = rmt_byg_save($tv['data']);
$row = rmt_byg_by_id($t1['id']);
ok('the trip date is stored', (string) $row['travel_date'], $in30);
ok('the page is stored', (string) $row['page'], '/blog/rome-tax-2026');
$in40 = date('Y-m-d', strtotime('+40 days'));
q_run('UPDATE byg_signups SET reminded_14 = ? WHERE id = ?', ['2026-01-01 00:00:00', $t1['id']]);
$t2 = rmt_byg_save(rmt_byg_validate(['email' => 'trip@example.com', 'topic' => 'tax', 'city' => 'rome', 'travel_date' => $in40])['data']);
ok('signing up again is the same row', $t2['id'], $t1['id']);
$row = rmt_byg_by_id($t1['id']);
ok('a new trip date replaces the old one', (string) $row['travel_date'], $in40);
ok('...and its reminders start again', $row['reminded_14'], null);
ok('...and the page is kept', (string) $row['page'], '/blog/rome-tax-2026');

$today = '2027-03-01';
$base = ['status' => 'active', 'travel_date' => '2027-03-15', 'reminded_14' => null, 'reminded_3' => null];
ok('14 days out, the 14 day reminder is due', rmt_byg_reminder_due($base, $today), 14);
ok('15 days out, nothing is due', rmt_byg_reminder_due(['travel_date' => '2027-03-16'] + $base, $today), null);
ok('10 days out with no reminder yet, the 14 day one still goes', rmt_byg_reminder_due(['travel_date' => '2027-03-11'] + $base, $today), 14);
ok('10 days out after the 14 day one, nothing', rmt_byg_reminder_due(['travel_date' => '2027-03-11', 'reminded_14' => 'x'] + $base, $today), null);
ok('3 days out, the 3 day reminder is due', rmt_byg_reminder_due(['travel_date' => '2027-03-04', 'reminded_14' => 'x'] + $base, $today), 3);
ok('2 days out with nothing sent, only the 3 day one goes', rmt_byg_reminder_due(['travel_date' => '2027-03-03'] + $base, $today), 3);
ok('after the 3 day one, nothing more', rmt_byg_reminder_due(['travel_date' => '2027-03-03', 'reminded_3' => 'x'] + $base, $today), null);
ok('a trip that already started gets nothing', rmt_byg_reminder_due(['travel_date' => '2027-02-27'] + $base, $today), null);
ok('an unconfirmed signup gets nothing', rmt_byg_reminder_due(['status' => 'pending'] + $base, $today), null);
ok('a switched off signup gets nothing', rmt_byg_reminder_due(['status' => 'off'] + $base, $today), null);
ok('no trip date, no reminder', rmt_byg_reminder_due(['travel_date' => null] + $base, $today), null);

q_run("UPDATE byg_signups SET status = 'active', travel_date = ?, reminded_14 = NULL, reminded_3 = NULL WHERE id = ?", [date('Y-m-d', strtotime('+5 days')), $t1['id']]);
$r = rmt_byg_remind(false);
ok('the daily run finds the reminder due', $r['due'], 1);
ok('a dry run sends nothing', $r['sent'], 0);
$r = rmt_byg_remind(true);
ok('with mail off, nothing counts as sent', $r['sent'], 0);
ok('...and the reminder is not stamped, so it goes tomorrow', rmt_byg_by_id($t1['id'])['reminded_14'], null);
$sum = rmt_byg_summary(['topic' => 'tax', 'city' => 'rome', 'city_name' => 'Rome', 'page' => '']);
ok('the email summary carries the city tax line', $sum['lines'][0] ?? '', 'Tourist tax in Rome: 6 euros.');
ok('...and links back to the free page', str_contains($sum['url'], 'tourist-tax-calculator?city=rome'), true);
$sum = rmt_byg_summary(['topic' => 'scams', 'city' => 'amsterdam-netherlands', 'city_name' => 'Amsterdam', 'page' => '/warnings/amsterdam-netherlands']);
ok('a warnings summary lists the warnings', $sum['lines'][0] ?? '', 'Fake police: y');
ok('...and links to the page it came from', str_contains($sum['url'], '/warnings/amsterdam-netherlands'), true);

echo $fail ? "\n$fail FAIL(S), $pass pass\n" : "\nALL PASS ($pass)\n";
exit($fail ? 1 : 0);
