<?php
/**
 * Regression tests for meetup Event markup (rmt_meetup_jsonld).
 *
 * A meetup is the only thing on this site that search engines have a rich result for -- a real,
 * dated, public event with a host and an attendee count -- and it was emitting no structured data
 * at all. It is also the one feature with a privacy promise that markup could quietly break, so
 * these tests pin both halves: that the event is described properly, and that it never describes
 * a location finer than the city.
 *
 *   php tests/meetup_event_schema_test.php   -> PASS/FAIL per case, exits non-zero on any failure.
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

$fails = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL  $name" . ($detail ? " -- $detail" : '') . "\n"; }
    else echo "PASS  $name\n";
}

$m = [
    'id' => 12, 'title' => 'Coffee by the river', 'status' => 'published',
    'description' => "Meeting outside the station at ten, then walking down.\nCoffee is on me.",
    'date_start' => '2027-04-02 10:00:00', 'date_end' => '2027-04-02 12:00:00',
    'capacity' => 8, 'dest_name' => 'Lisbon', 'dest_country' => 'Portugal',
    'host' => ['username' => 'ana'],
];
$ld = rmt_meetup_jsonld($m, 3);

ok('it is an Event', $ld['@type'] === 'Event');
ok('it is offline and scheduled', $ld['eventAttendanceMode'] === 'https://schema.org/OfflineEventAttendanceMode'
    && $ld['eventStatus'] === 'https://schema.org/EventScheduled');
ok('start and end are ISO 8601', str_starts_with($ld['startDate'], '2027-04-02T')
    && str_starts_with($ld['endDate'], '2027-04-02T'), json_encode([$ld['startDate'] ?? null, $ld['endDate'] ?? null]));
ok('it points at its own page', $ld['url'] === 'https://ruinmytrip.com/meetup/12', (string) $ld['url']);
ok('the host is the organizer', ($ld['organizer']['name'] ?? '') === '@ana');
ok('the description is plain text, not markup', !str_contains((string) $ld['description'], '<'));
ok('capacity is published as published', ($ld['maximumAttendeeCapacity'] ?? null) === 8);
ok('remaining capacity is capacity minus who is going', ($ld['remainingAttendeeCapacity'] ?? null) === 5);
ok('meetups are free', ($ld['isAccessibleForFree'] ?? null) === true);

// The promise: destination only. Nothing in the markup may be finer than the city.
$loc = $ld['location'] ?? [];
ok('the location is a Place with the city', ($loc['@type'] ?? '') === 'Place' && ($loc['name'] ?? '') === 'Lisbon');
ok('the address is locality and country only',
   array_keys($loc['address'] ?? []) === ['@type', 'addressLocality', 'addressCountry'],
   json_encode(array_keys($loc['address'] ?? [])));
$flat = json_encode($ld);
foreach (['streetAddress', 'geo', 'latitude', 'longitude'] as $forbidden) {
    ok("no $forbidden in the markup", !str_contains($flat, $forbidden));
}

// A cancelled meetup says so in the result, which is exactly who needs to hear it.
$cancelled = rmt_meetup_jsonld(['id' => 13, 'title' => 'Called off', 'status' => 'cancelled',
    'date_start' => '2027-04-02 10:00:00', 'date_end' => null, 'capacity' => 0,
    'dest_name' => 'Lisbon', 'dest_country' => 'Portugal', 'host' => ['username' => 'ana']]);
ok('cancelled is marked cancelled', $cancelled['eventStatus'] === 'https://schema.org/EventCancelled');
ok('no end date is simply absent', !array_key_exists('endDate', $cancelled));
ok('no capacity means no capacity claim', !array_key_exists('maximumAttendeeCapacity', $cancelled)
    && !array_key_exists('remainingAttendeeCapacity', $cancelled));

// A meetup with nothing attached still produces valid, honest markup.
$bare = rmt_meetup_jsonld(['id' => 14, 'title' => 'Somewhere', 'status' => 'published',
    'date_start' => '2027-05-01 09:00:00', 'date_end' => null, 'capacity' => 0]);
ok('a meetup with no city claims no location', !array_key_exists('location', $bare));
ok('a meetup with no host claims no organizer', !array_key_exists('organizer', $bare));

$controllers = (string) file_get_contents(BASE_PATH . '/app/controllers.php');
ok('the meetup page emits it', str_contains($controllers, 'jsonld(rmt_meetup_jsonld('));
ok('the meetup query carries the country', str_contains($controllers, 'd.country dest_country'));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
