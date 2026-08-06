<?php
/**
 * LOCAL DEV FIXTURES for the warnings system.
 *
 * THIS DATA IS FAKE AND MUST NEVER REACH PRODUCTION. It exists so the warning list, filters,
 * moderation queue, dashboard, alerts and homepage can be exercised under realistic volume
 * before any real traveler has submitted anything.
 *
 * It carries the same three independent production guards as scripts/dev_fixtures.php, and every
 * row it writes is identifiable and purgeable:
 *   - the author is a  fixture_*  user (@fixture.invalid)
 *   - warnings.source_url is set to the sentinel below
 *
 * Usage:
 *   php scripts/dev_warning_fixtures.php           # add ~60 warnings across the first destinations
 *   php scripts/dev_warning_fixtures.php --purge   # remove every fixture warning
 *   php scripts/dev_warning_fixtures.php --count
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('RMT_NO_AUTOSEED', true);
require BASE_PATH . '/app/loadconfig.php';
$GLOBALS['config'] = rmt_load_config();
require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/warnings.php';

function wfx_abort(string $why): never {
    fwrite(STDERR, "\n  REFUSED: {$why}\n  Fixtures are local-only synthetic data and must never touch production.\n\n");
    exit(1);
}
if (($GLOBALS['config']['app_env'] ?? '') === 'production') wfx_abort('APP_ENV is production.');
if (getenv('DATABASE_URL')) wfx_abort('DATABASE_URL is set (points at a managed Postgres).');
if (($GLOBALS['config']['db_driver'] ?? '') !== 'sqlite') wfx_abort('db_driver is not sqlite.');

const WFX_SENTINEL = 'fixture://local-dev-only';

$mode = $argv[1] ?? '';
$pdo = db();

if ($mode === '--count') {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM warnings WHERE source_url = '" . WFX_SENTINEL . "'")->fetchColumn();
    fwrite(STDOUT, "fixture warnings: {$n}\n");
    exit(0);
}
if ($mode === '--purge') {
    $st = $pdo->prepare('DELETE FROM warnings WHERE source_url = ?');
    $st->execute([WFX_SENTINEL]);
    fwrite(STDOUT, "purged {$st->rowCount()} fixture warnings\n");
    exit(0);
}

/* Deterministic PRNG so repeated runs produce the same data. */
mt_srand(20260806);

$dests = $pdo->query('SELECT id, name FROM destinations ORDER BY id LIMIT 12')->fetchAll();
if (!$dests) wfx_abort('no destinations in the database.');

/* A fixture author. Reuses one from dev_fixtures.php if present, otherwise makes one. */
$author = q_one("SELECT id FROM users WHERE username LIKE 'fixture_%' ORDER BY id LIMIT 1");
if (!$author) {
    $id = (int) q_run('INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES (?,?,?,?,?,?)',
        ['fixture_warner', 'fixture_warner@fixture.invalid', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
         'user', 'active', date('Y-m-d H:i:s')]);
    q_run('INSERT INTO profiles (user_id, display_name) VALUES (?,?)', [$id, 'Fixture Warner']);
    $author = ['id' => $id];
}
$uid = (int) $author['id'];

/* Templates per category. Written as plausible-but-obviously-synthetic text; they are never
   published anywhere but a local machine. */
$templates = [
    'scams' => [
        ['Taxi at the airport refused the meter', 'The driver waved off the meter and quoted a flat fare about four times what the ride should cost. Insisting on the meter got a shrug and an offer to find another car.', 'Use the official rank and agree on the meter before the boot is opened.', 3, 60],
        ['Fake ticket seller outside the main attraction', 'A man in a lanyard sold us skip-the-line tickets that turned out to be photocopies. The gate refused them and the seller was gone.', 'Buy only from the official site or the ticket office itself.', 3, 90],
        ['Bracelet pushed onto my wrist then demanded payment', 'Someone tied a friendship bracelet on before I could pull away, then blocked the path asking for money.', 'Keep hands in pockets near the cathedral steps and do not stop walking.', 1, 15],
    ],
    'hidden-costs' => [
        ['Resort fee not shown until check-in', 'The nightly rate looked fine online. At check-in there was a mandatory daily resort fee that added a substantial amount over four nights.', 'Search the hotel name plus "resort fee" before booking, and read the fine print at the bottom of the rate page.', 3, 160],
        ['City tourist tax charged in cash only', 'The tourist tax was per person per night and had to be paid in cash at checkout, which nobody mentioned at any point.', 'Keep small notes for the tax; it is rarely included in the booking total.', 2, 40],
        ['Card surcharge on every restaurant bill', 'Several places added a card processing fee. It was on the bill but never on the menu.', 'Ask before ordering, or carry some cash.', 1, 12],
    ],
    'transportation' => [
        ['Bought the wrong metro ticket type', 'The zone system is not obvious and the single ticket did not cover the airport line. An inspector fined us on the spot.', 'Check the zone map before buying; airport lines are usually a separate fare.', 2, 70],
        ['Rideshare pickup banned at the terminal', 'The app kept assigning drivers who could not stop at arrivals, so we waited a long time in a car park before giving up.', 'Check whether rideshare is allowed at the terminal before you land.', 2, 35],
        ['Rail strike cancelled our connection', 'A strike had been announced a week earlier but not by the booking site, and the connection simply did not run.', 'Check the national rail operator site for strike notices before travel days.', 4, 220],
    ],
    'closures' => [
        ['Main sight covered in restoration scaffolding', 'The facade was completely wrapped. Nothing on the booking page mentioned it, and the tickets were non-refundable.', 'Search recent photos before booking timed entry.', 2, 30],
        ['Museum closed on the day we planned', 'Closed one weekday a week, which the aggregator we booked through did not reflect.', 'Confirm opening days on the official site, not on an aggregator.', 2, 0],
    ],
    'crowds' => [
        ['Cruise ship day turned the old town into a queue', 'Three ships were in. Every narrow street was gridlocked from mid-morning until late afternoon.', 'Check the port schedule and plan the old town for an evening instead.', 2, 0],
        ['Timed entry sold out three weeks ahead', 'We assumed we could buy on the day. Everything was gone and resale was extortionate.', 'Book the headline sights the moment your dates are fixed.', 3, 0],
    ],
    'neighborhoods' => [
        ['Booked next to the nightlife strip and got no sleep', 'The listing said central. It was above a bar street that ran until 4am every night of the week.', 'Search the street name plus "noise" and check the map for bars before booking.', 3, 0],
        ['Looked central on the map, was a long way from anything', 'The map scale hid a large park between us and the centre. Every trip in was a 40 minute walk or a taxi.', 'Measure the walking time to two places you actually plan to visit.', 2, 45],
    ],
    'weather' => [
        ['Rainy season meant afternoon washouts every day', 'Mornings were fine, but from about 2pm it rained hard enough to end the day, every day of the trip.', 'Plan outdoor activities for mornings in this season, or shift the dates.', 2, 0],
        ['Heat made the middle of the day unusable', 'Well above forty degrees. Walking anywhere between noon and five was not realistic.', 'Start early, rest in the afternoon, and book accommodation with real air conditioning.', 3, 0],
    ],
    'health-safety' => [
        ['Phone taken from a cafe table', 'Set the phone down on an outside table for a moment. Someone walked past and it was gone.', 'Nothing on the table outside. Bag strap through your leg.', 3, 700],
        ['Pickpocketed on a crowded tram', 'The tram to the main sight was packed. My wallet was gone by the second stop.', 'Front pockets only on that line, and keep a card separate from the wallet.', 3, 200],
    ],
    'entry-requirements' => [
        ['Travel authorisation needed and nobody told us', 'Found out at the airport that an online authorisation was required in advance. Missed the flight sorting it.', 'Check the official government entry page for your nationality a month before travel.', 4, 400],
        ['Passport validity rule caught us out', 'Needed six months validity beyond arrival. Ours had four. Denied boarding.', 'Check validity rules, not just the expiry date.', 4, 600],
    ],
    'accommodation' => [
        ['Listing photos were of a different apartment', 'The apartment we arrived at was smaller, darker, and on a different street than the one advertised.', 'Reverse image search the listing photos, and check reviews that mention the actual address.', 3, 0],
        ['Cleaning fee and deposit added at the door', 'A cash cleaning fee and a security deposit were demanded on arrival, neither mentioned in the booking.', 'Message the host before booking and get the full total in writing.', 2, 120],
    ],
];

$travelerTypes = array_keys(RMT_TRAVELER_TYPES);
$now = time();
$made = 0;

foreach ($dests as $di => $d) {
    foreach ($templates as $cat => $rows) {
        // Not every destination gets every category — a uniform grid would hide bugs in the
        // "this destination has no warnings in that category" paths.
        if ((($di + crc32($cat)) % 3) === 0) continue;
        $t = $rows[mt_rand(0, count($rows) - 1)];
        [$title, $body, $advice, $severity, $cost] = $t;

        $daysAgo = mt_rand(5, 300);
        $created = date('Y-m-d H:i:s', $now - $daysAgo * 86400);
        $expMonth = date('Y-m', $now - ($daysAgo + mt_rand(10, 120)) * 86400);
        // Most fixtures are approved so the public pages have content; a few sit in the queue so
        // the moderation screens are exercisable too.
        $status = (mt_rand(1, 10) <= 8) ? 'approved' : 'pending';
        $ver = ($status === 'approved' && mt_rand(1, 6) === 1) ? 'verified' : 'unverified';

        $id = (int) q_run('INSERT INTO warnings
            (user_id,destination_id,title,slug,category,body,advice,severity,date_experienced,season_month,
             location_detail,cost_impact_usd,traveler_type,attested,status,verification,helpful_count,
             source_url,dedupe_hash,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?,?,?,?)', [
            $uid, (int) $d['id'], $title, '', $cat, $body . "\n\n" . $d['name'] . ', fixture data.', $advice,
            $severity, $expMonth, (int) date('n', strtotime($expMonth . '-01')),
            null, $cost ?: null, $travelerTypes[mt_rand(0, count($travelerTypes) - 1)],
            $status, $ver, ($status === 'approved' ? mt_rand(0, 24) : 0),
            WFX_SENTINEL, hash('sha256', $d['id'] . $cat . $title . $di), $created, $created,
        ]);
        q_exec('UPDATE warnings SET slug = ? WHERE id = ?', [mb_substr(slugify($title), 0, 70), $id]);
        $made++;
    }
}

fwrite(STDOUT, "created {$made} fixture warnings across " . count($dests) . " destinations\n");
fwrite(STDOUT, "purge with: php scripts/dev_warning_fixtures.php --purge\n");
