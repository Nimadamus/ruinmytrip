<?php
/**
 * Regression tests for hosting a meetup (migration 043 / app/meetups.php).
 *
 * Meetups are the one thing on this site that put two strangers in the same physical place, so the
 * rules are not incidental detail and none of them are allowed to become decorative:
 *
 *   - capacity is a number the site PUBLISHES. Before this it was stored and never read, so a
 *     meetup that said 8 accepted forty. Eight people had planned around a promise nobody kept.
 *   - a meetup in the past must not still be taking RSVPs; people simply turn up.
 *   - a cancelled meetup keeps its page and stops taking RSVPs. It must not 404 on the people who
 *     had already arranged their day around it.
 *   - the safety acknowledgement is required to host, and only the host can edit or cancel.
 *
 * Runs against a throwaway in-memory SQLite DB. No network, no fixtures on disk.
 *
 *   php tests/meetup_hosting_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/meetups.php';

function dest_by_id(int $id): ?array { return q_one('SELECT * FROM destinations WHERE id = ?', [$id]); }

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT)');
$pdo->exec('CREATE TABLE meetups (id INTEGER PRIMARY KEY AUTOINCREMENT, host_id INT NOT NULL,
              destination_id INT, title TEXT NOT NULL, description TEXT, date_start TEXT, date_end TEXT,
              visibility TEXT NOT NULL DEFAULT \'public\', capacity INTEGER DEFAULT 0,
              safety_ack INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'published\',
              created_at TEXT NOT NULL, updated_at TEXT)');
$pdo->exec('CREATE TABLE meetup_rsvps (meetup_id INT NOT NULL, user_id INT NOT NULL,
              status TEXT NOT NULL DEFAULT \'going\', PRIMARY KEY (meetup_id, user_id))');
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'lisbon-portugal','Lisbon','Portugal')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if ($cond) { echo "  PASS  $name\n"; return; }
    $fails++;
    echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

$future = date('Y-m-d H:i:s', time() + 86400 * 7);
$later  = date('Y-m-d H:i:s', time() + 86400 * 7 + 7200);
$past   = date('Y-m-d H:i:s', time() - 86400 * 7);

/** A submission that passes everything, so each case below changes exactly one thing. */
$good = static fn(array $over = []): array => $over + [
    'title' => 'Canal walk and coffee',
    'description' => 'Meet outside the west entrance of the station at ten, we walk the canals and stop for coffee.',
    'destination_id' => 1, 'date_start' => $future, 'date_end' => $later,
    'capacity' => 6, 'safety_ack' => 1,
];
/** Does validation reject $in, and does it say why? */
$rejects = static function (array $in, string $needle) use (&$fails): bool {
    $v = rmt_meetup_validate($in);
    if ($v['ok']) return false;
    foreach ($v['errors'] as $e) if (stripos($e, $needle) !== false) return true;
    return false;
};

echo "meetup hosting\n";

// --- The happy path ---------------------------------------------------------------------------
$v = rmt_meetup_validate($good());
ok('a complete meetup validates', $v['ok'], implode(' | ', $v['errors']));
ok('the start time is normalised to the schema shape',
   $v['data']['date_start'] === $future, $v['data']['date_start']);
ok('an omitted end time stays null', rmt_meetup_validate($good(['date_end' => '']))['data']['date_end'] === null);
ok('capacity 0 means no limit', rmt_meetup_validate($good(['capacity' => 0]))['ok']);

// --- What it refuses --------------------------------------------------------------------------
ok('a title that says nothing is refused',      $rejects($good(['title' => 'hi']), 'title'));
ok('an over-long title is refused',             $rejects($good(['title' => str_repeat('a', 141)]), 'too long'));
ok('a description with no plan in it is refused', $rejects($good(['description' => 'come along']), 'plan'));
ok('no destination is refused',                 $rejects($good(['destination_id' => 0]), 'destination'));
ok('a destination that does not exist is refused', $rejects($good(['destination_id' => 999]), 'does not exist'));
ok('no date is refused',                        $rejects($good(['date_start' => '']), 'date'));
ok('an unparseable date is refused',            $rejects($good(['date_start' => 'next tuesday-ish']), 'real date'));
ok('a date in the past is refused on a new meetup', $rejects($good(['date_start' => $past]), 'future'));
ok('an end before the start is refused',        $rejects($good(['date_end' => date('Y-m-d H:i:s', time() + 3600)]), 'after the start'));
ok('a capacity of one is refused',              $rejects($good(['capacity' => 1]), 'Capacity'));
ok('a capacity over the cap is refused',        $rejects($good(['capacity' => RMT_MEETUP_CAPACITY_MAX + 1]), 'Capacity'));
// The safety acknowledgement is the whole reason the column exists. It had never been written to.
ok('hosting without acknowledging the safety terms is refused', $rejects($good(['safety_ack' => 0]), 'safety'));
ok('the acknowledgement is recorded, not just checked', $v['data']['safety_ack'] === 1);

// --- Editing something that has already happened ----------------------------------------------
// Forcing a host to move a past meetup into the future in order to fix a typo would be absurd, and
// the past date is the truth about that meetup.
ok('an unchanged past start is allowed on an edit', rmt_meetup_validate($good(['date_start' => $past]), $past)['ok']);
ok('but moving it to a DIFFERENT past date is still refused',
   !rmt_meetup_validate($good(['date_start' => date('Y-m-d H:i:s', time() - 3600)]), $past)['ok']);

// --- Capacity, the number the site publishes ---------------------------------------------------
q_run("INSERT INTO meetups (id,host_id,destination_id,title,description,date_start,capacity,safety_ack,status,created_at)
       VALUES (1,10,1,'Canal walk','plan',?,3,1,'published',?)", [$future, date('Y-m-d H:i:s')]);
$m = q_one('SELECT * FROM meetups WHERE id=1');
ok('an empty meetup is not full', !rmt_meetup_is_full($m));
foreach ([21, 22] as $u) q_run("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (1,?,'going')", [$u]);
ok('the going count is counted, never stored', rmt_meetup_going_count(1) === 2);
ok('under capacity is not full', !rmt_meetup_is_full($m));
q_run("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (1,23,'going')");
ok('at capacity is full', rmt_meetup_is_full($m), 'going=' . rmt_meetup_going_count(1));
ok('a passed-in count is used instead of re-counting', rmt_meetup_is_full($m, 1) === false);

q_run("INSERT INTO meetups (id,host_id,destination_id,title,description,date_start,capacity,safety_ack,status,created_at)
       VALUES (2,10,1,'Open one','plan',?,0,1,'published',?)", [$future, date('Y-m-d H:i:s')]);
$open = q_one('SELECT * FROM meetups WHERE id=2');
foreach (range(31, 40) as $u) q_run("INSERT INTO meetup_rsvps (meetup_id,user_id,status) VALUES (2,?,'going')", [$u]);
ok('capacity 0 is never full, however many go', !rmt_meetup_is_full($open), 'going=' . rmt_meetup_going_count(2));

// --- Past --------------------------------------------------------------------------------------
ok('a future meetup is not past', !rmt_meetup_is_past($m));
ok('a meetup whose start has gone by is past', rmt_meetup_is_past(['date_start' => $past]));
ok('a meetup with no date is not treated as past', !rmt_meetup_is_past(['date_start' => '']));

// --- Who may edit --------------------------------------------------------------------------------
ok('the host may edit',            rmt_meetup_can_edit($m, ['id' => 10, 'role' => 'user']));
ok('another member may not',       !rmt_meetup_can_edit($m, ['id' => 11, 'role' => 'user']));
ok('a logged-out visitor may not', !rmt_meetup_can_edit($m, null));
// Moderators act through the report queue, which removes content. Silently editing somebody's
// meetup out from under them is a different power and this is not where it lives.
ok('an admin may not edit it here', !rmt_meetup_can_edit($m, ['id' => 12, 'role' => 'admin']));

// --- The controller keeps its guards -----------------------------------------------------------
$src = file_get_contents(BASE_PATH . '/app/controllers.php');
$body = static function (string $name) use ($src): string {
    $start = strpos($src, "function {$name}(");
    if ($start === false) return '';
    $end = strpos($src, "\nfunction ", $start + 1);
    return substr($src, $start, ($end ?: strlen($src)) - $start);
};
foreach (['meetup_new_form', 'meetup_create', 'meetup_edit_form', 'meetup_edit_submit', 'meetup_cancel'] as $fn) {
    ok("$fn() exists", $body($fn) !== '');
}
ok('meetup_create(): 18+ gate',            strpos($body('meetup_create'), 'can_host_meetups') !== false);
ok('meetup_create(): verified email',      strpos($body('meetup_create'), 'require_verified_email') !== false);
ok('meetup_create(): CSRF',                strpos($body('meetup_create'), 'csrf_check()') !== false);
ok('meetup_create(): one submit only',     strpos($body('meetup_create'), 'rmt_submit_ok') !== false);
ok('meetup_create(): rate limited',        strpos($body('meetup_create'), 'rmt_rate_ok') !== false);
ok('meetup_create(): host is RSVPed in',   strpos($body('meetup_create'), 'meetup_rsvps') !== false);
ok('meetup_edit_submit(): host only',      strpos($body('meetup_edit_submit'), 'rmt_meetup_can_edit') !== false);
ok('meetup_edit_submit(): capacity cannot drop below those already going',
   strpos($body('meetup_edit_submit'), 'rmt_meetup_going_count') !== false);
ok('meetup_cancel(): host only',           strpos($body('meetup_cancel'), 'rmt_meetup_can_edit') !== false);
// Cancelling must not reuse the guide/trip 'removed' soft delete, which makes the page 404.
ok('meetup_cancel(): cancels rather than deleting',
   strpos($body('meetup_cancel'), "'cancelled'") !== false && strpos($body('meetup_cancel'), "'removed'") === false);
ok('meetup_rsvp(): refuses a cancelled meetup', strpos($body('meetup_rsvp'), "'cancelled'") !== false);
ok('meetup_rsvp(): refuses one that already happened', strpos($body('meetup_rsvp'), 'rmt_meetup_is_past') !== false);
ok('meetup_rsvp(): enforces capacity on the server', strpos($body('meetup_rsvp'), 'rmt_meetup_is_full') !== false);

$routes = file_get_contents(BASE_PATH . '/public/index.php');
foreach (['meetup_new_form', 'meetup_create', 'meetup_edit_form', 'meetup_edit_submit', 'meetup_cancel'] as $fn) {
    ok("$fn is routed", strpos($routes, "'{$fn}'") !== false);
}
// /meetup/new must be matched before /meetup/{id}, or "new" is read as an id and 404s.
ok('/meetup/new is routed before /meetup/{id}',
   strpos($routes, "#^/meetup/new\$#") < strpos($routes, "#^/meetup/(?<id>"));

echo $fails ? "\n$fails FAILED\n" : "\nAll meetup hosting tests passed.\n";
exit($fails ? 1 : 0);
