<?php
/**
 * Regression tests for the join path (rmt_join_intent_line, the homepage, the register form).
 *
 * The site's front door sold research: "what it actually costs, what nearly ruins it". That is the
 * sentence every travel page on the internet already says, it is not what this site is, and 28 days
 * of it earned 1104 impressions and zero clicks. The one thing here a reader cannot get from the
 * other nine results is the people, so that is what the front door and the join form now say.
 *
 * What must hold:
 *   - the join form finishes the sentence the visitor started: arriving from a city's travelers
 *     page names that city, and arriving from nowhere in particular says nothing rather than
 *     inventing a reason.
 *   - a return path that is not one we recognise never reaches the page as text.
 *   - the homepage leads with people and asks for a member, and does not go back to selling guides.
 *
 *   php tests/join_conversion_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

// auth.php is only needed for the one pure helper; stub what its other functions lean on.
require BASE_PATH . '/app/auth.php';

$pdo = db();
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT)');
$pdo->exec("INSERT INTO destinations (id,slug,name) VALUES (1,'lisbon-portugal','Lisbon')");

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$line = rmt_join_intent_line('/d/lisbon-portugal/travelers');
ok('a city travelers page names the city', is_string($line) && str_contains($line, 'Lisbon'), (string) $line);
ok('dates page asks for dates', str_contains((string) rmt_join_intent_line('/going'), 'post your dates'));
ok('matches page says what it does', str_contains((string) rmt_join_intent_line('/matches'), 'overlap'));
ok('a meetup says it is public and 18+', str_contains((string) rmt_join_intent_line('/meetup/4'), '18+'));
ok('talk asks the question', str_contains((string) rmt_join_intent_line('/talk'), 'ask'));

// Unknown, empty and hostile inputs say nothing rather than guessing.
ok('an unknown path says nothing', rmt_join_intent_line('/blog/whatever') === null);
ok('empty says nothing', rmt_join_intent_line('') === null);
ok('a city we do not have says nothing', rmt_join_intent_line('/d/atlantis/travelers') === null);
ok('a query string cannot smuggle a path', rmt_join_intent_line('/blog/x?return=/going') === null);

// The homepage argument. Checked as source text: these are the sentences the steer was about.
$home = (string) file_get_contents(BASE_PATH . '/views/home.php');
ok('the hero leads with people', str_contains($home, 'Find the people going where you are going.'));
ok('the old cost pitch is gone', !str_contains($home, 'What it actually costs. What nearly ruins it.'));
ok('the hero asks for a member', str_contains($home, 'Join free'));
ok('who is going is above the research', strpos($home, 'Travelers with dates coming up') < strpos($home, '2026 travel guides'));
ok('the homepage sends people to city hubs', str_contains($home, "/travelers'"));
$reg = (string) file_get_contents(BASE_PATH . '/views/auth/register.php');
ok('the form says what the site is', str_contains($reg, 'travel community'));
ok('the form uses the intent line', str_contains($reg, 'rmt_join_intent_line'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
