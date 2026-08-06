<?php
/**
 * Regression tests for the watchlist / alert layer.
 *
 * The rules that matter here are all "do not become spam" rules, and every one of them is meant
 * to hold in DATA rather than in the sender's control flow — so these tests drive the data layer
 * directly rather than the script.
 *
 *   php tests/alerts_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:', 'security_salt' => 'test-salt',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/warnings.php';
require BASE_PATH . '/app/alerts.php';

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, hero_url TEXT, risk_level INT)');
$pdo->exec("INSERT INTO destinations (id,slug,name,country) VALUES (1,'paris-france','Paris','France')");
$pdo->exec('CREATE TABLE warnings (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
            title TEXT, slug TEXT, category TEXT, body TEXT, severity INT, date_experienced TEXT,
            status TEXT, verification TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE trip_watchlist (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, destination_id INT,
            label TEXT, date_from TEXT, date_to TEXT, note TEXT, categories_json TEXT, min_severity INT DEFAULT 1,
            alert_frequency TEXT DEFAULT "weekly", last_alerted_at TEXT, last_seen_at TEXT,
            created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE alert_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, user_id INT,
            destination_id INT, categories_json TEXT, min_severity INT DEFAULT 2, frequency TEXT DEFAULT "weekly",
            token TEXT, source TEXT, confirmed_at TEXT, unsubscribed_at TEXT, last_sent_at TEXT, created_at TEXT)');
$pdo->exec('CREATE UNIQUE INDEX idx_alertsub_uniq ON alert_subscriptions(email, destination_id)');
$pdo->exec('CREATE TABLE alert_deliveries (id INTEGER PRIMARY KEY AUTOINCREMENT, channel TEXT, recipient TEXT,
            warning_id INT, watchlist_id INT, subscription_id INT, created_at TEXT)');
$pdo->exec('CREATE UNIQUE INDEX idx_alert_deliv_uniq ON alert_deliveries(recipient, warning_id, channel)');

$fail = 0;
$check = function (string $name, $got, $expect) use (&$fail) {
    $ok = $got === $expect;
    printf("  [%s] %-60s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        var_export($expect, true), var_export($got, true));
    if (!$ok) $fail++;
};

$now = date('Y-m-d H:i:s');
$mk = function (string $cat, int $sev, string $created) use ($pdo): int {
    return (int) q_run("INSERT INTO warnings (user_id,destination_id,title,slug,category,body,severity,
                        status,verification,created_at) VALUES (1,1,?,?,?,'body',?,'approved','unverified',?)",
        ['W ' . $cat . $sev, 'w', $cat, $sev, $created]);
};

echo "-- date validation --\n";
$v = rmt_watchlist_validate_dates('2026-11-02', '2026-11-09');
$check('a normal range passes', $v['ok'], true);
$check('no dates at all is allowed', rmt_watchlist_validate_dates('', '')['ok'], true);
$check('return before departure fails', rmt_watchlist_validate_dates('2026-11-09', '2026-11-02')['ok'], false);
$check('a non-date fails', rmt_watchlist_validate_dates('next tuesday-ish', '')['ok'], false);
$check('a date decades out fails (likely a typo)', rmt_watchlist_validate_dates('2099-01-01', '')['ok'], false);

echo "\n-- category encoding --\n";
$check('unknown categories are dropped', rmt_categories_encode(['scams', 'not-real']), '["scams"]');
$check('an empty list encodes as null (= all categories)', rmt_categories_encode([]), null);
$check('decode round-trips', rmt_categories_decode('["scams","crowds"]'), ['scams', 'crowds']);
$check('decode of garbage is empty', rmt_categories_decode('not json'), []);
$check('decode filters unknown values', rmt_categories_decode('["scams","wizardry"]'), ['scams']);

echo "\n-- what counts as new for a watcher --\n";
$old = date('Y-m-d H:i:s', strtotime('-30 days'));
$seen = date('Y-m-d H:i:s', strtotime('-10 days'));
$w1 = $mk('scams', 3, date('Y-m-d H:i:s', strtotime('-20 days')));  // before last_seen
$w2 = $mk('scams', 3, date('Y-m-d H:i:s', strtotime('-2 days')));   // after  last_seen
$w3 = $mk('crowds', 1, date('Y-m-d H:i:s', strtotime('-1 day')));   // after, but low severity
$watch = ['destination_id' => 1, 'created_at' => $old, 'last_seen_at' => $seen,
          'min_severity' => 1, 'categories_json' => null];
$ids = array_column(rmt_new_warnings_for($watch), 'id');
$check('only warnings after last_seen are new', $ids, [$w2, $w3]);

$watch['min_severity'] = 3;
$check('the severity floor is respected', array_column(rmt_new_warnings_for($watch), 'id'), [$w2]);

$watch['min_severity'] = 1;
$watch['categories_json'] = '["crowds"]';
$check('the category filter is respected', array_column(rmt_new_warnings_for($watch), 'id'), [$w3]);

$watch['categories_json'] = null;
$watch['last_seen_at'] = null;
$check('with no last_seen it falls back to created_at, not all time',
    count(rmt_new_warnings_for($watch)), 3);

echo "\n-- the delivery log is the real double-send guard --\n";
$check('first delivery is recorded', rmt_alert_log_delivery('a@example.test', $w2), true);
$check('the same warning to the same address is refused', rmt_alert_log_delivery('a@example.test', $w2), false);
$check('a different warning to the same address is fine', rmt_alert_log_delivery('a@example.test', $w3), true);
$check('the same warning to a different address is fine', rmt_alert_log_delivery('b@example.test', $w2), true);
$check('a different channel is tracked separately', rmt_alert_log_delivery('a@example.test', $w2, 'push'), true);

echo "\n-- frequency windows --\n";
$check('immediate is one hour', rmt_alert_window_hours('immediate'), 1);
$check('daily is under 24h so a daily cron never skips a day', rmt_alert_window_hours('daily'), 20);
$check('weekly is under 7d for the same reason', rmt_alert_window_hours('weekly'), 144);
$check('"none" means never', rmt_alert_window_hours('none'), null);
$check('an unknown frequency means never', rmt_alert_window_hours('hourly'), null);
$check('"none" closes the window even for a fresh address',
    rmt_alert_window_open('never@example.test', 'none'), false);
$check('a never-contacted address has an open window',
    rmt_alert_window_open('fresh@example.test', 'weekly'), true);
$check('an address contacted just now is inside its weekly window',
    rmt_alert_window_open('a@example.test', 'weekly'), false);

echo "\n-- subscriptions --\n";
$r = rmt_alert_subscribe('Reader@Example.test', 1, ['frequency' => 'weekly', 'min_severity' => 2]);
$check('a new subscription is created', $r['status'], 'created');
$check('the email is normalised to lower case', $r['row']['email'], 'reader@example.test');
$check('a new subscription starts UNCONFIRMED', $r['row']['confirmed_at'], null);

$r2 = rmt_alert_subscribe('reader@example.test', 1, ['frequency' => 'daily']);
$check('subscribing again does not create a second row', $r2['status'], 'reconfirm');
$check('still exactly one row', (int) q_one('SELECT COUNT(*) c FROM alert_subscriptions')['c'], 1);

q_exec('UPDATE alert_subscriptions SET confirmed_at = ? WHERE id = ?', [$now, (int) $r['row']['id']]);
$r3 = rmt_alert_subscribe('reader@example.test', 1, []);
$check('an already-confirmed address reports "exists"', $r3['status'], 'exists');

echo "\n-- unsubscribe tokens --\n";
$row = q_one('SELECT * FROM alert_subscriptions WHERE id = ?', [(int) $r['row']['id']]);
$check('a correct token resolves', rmt_alert_by_token('reader@example.test', (string) $row['token'])['id'], $row['id']);
$check('a wrong token does not', rmt_alert_by_token('reader@example.test', 'nope'), null);
$check('the token is case-insensitive on the address',
    rmt_alert_by_token('READER@example.test', (string) $row['token'])['id'], $row['id']);
$check('an unknown address resolves to null', rmt_alert_by_token('nobody@example.test', (string) $row['token']), null);
$check('the token differs per destination',
    rmt_alert_token('reader@example.test', 1) === rmt_alert_token('reader@example.test', 2), false);

echo "\n-- preparation checklist is derived from real warnings --\n";
$prep = rmt_trip_prep_actions(['destination_id' => 1, 'date_from' => date('Y-m-d', strtotime('+20 days'))]);
$labels = array_column($prep, 'label');
$check('a scams-only destination does not suggest visa homework',
    in_array('Check entry requirements and passport validity', $labels, true), false);
// This previously asserted the checklist came back EMPTY for a scams/crowds destination. That was
// asserting a gap, not a behaviour: rmt_trip_prep_actions() covered only six of the ten warning
// categories, so a destination warned about scams — the single most common category — produced no
// checklist and the dashboard silently rendered nothing. Every category now yields an action, so
// the correct assertion is that the checklist is populated and category-appropriate.
$check('a scams warning does produce an action', count($prep) > 0, true);
$check('and it is the scams-specific one',
    in_array('Read the reported scams before you arrive', $labels, true), true);
$check('crowds also produce their own action',
    in_array('Book timed entry for the busiest sights now', $labels, true), true);
$mk('entry-requirements', 4, $now);
$mk('neighborhoods', 2, $now);
$prep = rmt_trip_prep_actions(['destination_id' => 1, 'date_from' => date('Y-m-d', strtotime('+20 days'))]);
$labels = array_column($prep, 'label');
$check('entry-requirement warnings add a document check',
    in_array('Check entry requirements and passport validity', $labels, true), true);
$check('a trip 20 days out flags the neighbourhood check as urgent',
    $prep[array_search('Check the neighbourhood before you confirm accommodation', $labels, true)]['urgent'], true);
$far = rmt_trip_prep_actions(['destination_id' => 1, 'date_from' => date('Y-m-d', strtotime('+300 days'))]);
$farLabels = array_column($far, 'label');
$check('the same check is not urgent 300 days out',
    $far[array_search('Check the neighbourhood before you confirm accommodation', $farLabels, true)]['urgent'], false);

echo "\n" . ($fail === 0 ? "ALL PASS\n" : "{$fail} FAILURE(S)\n");
exit($fail === 0 ? 0 : 1);
