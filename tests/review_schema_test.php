<?php
/**
 * Google Review rich result: itemReviewed must be one of a closed list of types.
 *
 * Regression guard for the Search Console error "Invalid object type for field itemReviewed",
 * which every /review/ page produced while itemReviewed was hardcoded to schema.org Place, and
 * which every attraction /p/ page produced by hanging a review/aggregateRating off a
 * TouristAttraction. Both are real, accurate schema.org types; neither is review-eligible.
 *
 *   php tests/review_schema_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/editorial.php';
require BASE_PATH . '/app/places.php';
require BASE_PATH . '/app/reviews.php';
require BASE_PATH . '/app/seo.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-62s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

/** The types Google documents as valid for Review.itemReviewed / AggregateRating.itemReviewed. */
const GOOGLE_REVIEW_TYPES = [
    'Book', 'Course', 'CreativeWorkSeason', 'CreativeWorkSeries', 'Episode', 'Event', 'Game',
    'HowTo', 'LocalBusiness', 'MediaObject', 'Movie', 'MusicPlaylist', 'MusicRecording',
    'Organization', 'Product', 'Recipe', 'SoftwareApplication',
    // LocalBusiness subtypes we actually emit.
    'Hotel', 'Restaurant',
];

echo "-- eligible types --\n";
check('hotel maps to Hotel',            rmt_place_review_type('hotel'), 'Hotel');
check('restaurant maps to Restaurant',  rmt_place_review_type('restaurant'), 'Restaurant');
check('attraction is not eligible',     rmt_place_review_type('attraction'), null);
check('experience is not eligible',     rmt_place_review_type('experience'), null);
check('unknown type is not eligible',   rmt_place_review_type('beach'), null);
check('no eligible type is outside Google\'s list',
      array_values(array_diff(array_filter([
          rmt_place_review_type('hotel'), rmt_place_review_type('restaurant'),
      ]), GOOGLE_REVIEW_TYPES)), []);
check('page @type stays semantically accurate for attractions',
      rmt_place_schema_type('attraction'), 'TouristAttraction');

echo "\n-- review JSON-LD --\n";
$base = [
    'status' => 'published', 'rating' => 4, 'title' => 'Fine', 'body' => 'A body.',
    'username' => 'traveler', 'created_at' => '2026-08-01 10:00:00',
    'subject_name' => 'Somewhere', 'dest_name' => 'Barcelona', 'dest_country' => 'Spain',
];

$hotel = rmt_review_jsonld($base + ['place_type' => 'hotel', 'place_slug' => 'hotel-x', 'place_name' => 'Hotel X']);
check('hotel review emits Review markup', str_contains($hotel, '"@type":"Review"'), true);
check('hotel itemReviewed is Hotel', str_contains($hotel, '"itemReviewed":{"@type":"Hotel"'), true);
check('hotel itemReviewed has a name', str_contains($hotel, '"name":"Hotel X"'), true);
check('hotel itemReviewed has a url', str_contains($hotel, 'https://example.test/p/hotel-x'), true);
check('hotel itemReviewed carries an address', str_contains($hotel, '"addressLocality":"Barcelona"'), true);
check('no Place type anywhere in the payload', str_contains($hotel, '"@type":"Place"'), false);

$rest = rmt_review_jsonld($base + ['place_type' => 'restaurant', 'place_slug' => 'r-x', 'place_name' => 'R']);
check('restaurant itemReviewed is Restaurant', str_contains($rest, '"itemReviewed":{"@type":"Restaurant"'), true);

check('attraction review emits no Review markup',
      rmt_review_jsonld($base + ['place_type' => 'attraction', 'place_slug' => 'a-x', 'place_name' => 'A']), '');
check('destination-level review emits no Review markup',
      rmt_review_jsonld($base + ['place_type' => null, 'place_slug' => null, 'place_name' => null]), '');
check('unpublished review emits no Review markup',
      rmt_review_jsonld(['status' => 'draft'] + $base + ['place_type' => 'hotel', 'place_slug' => 'h', 'place_name' => 'H']), '');

echo "\n-- payload is valid JSON-LD --\n";
$decoded = json_decode(strip_tags($hotel), true);
check('decodes', is_array($decoded), true);
check('has @context', $decoded['@context'] ?? null, 'https://schema.org');
check('itemReviewed type is Google-supported',
      in_array($decoded['itemReviewed']['@type'] ?? '', GOOGLE_REVIEW_TYPES, true), true);
check('rating is bounded 1-5', ($decoded['reviewRating']['bestRating'] ?? null) === 5
      && ($decoded['reviewRating']['worstRating'] ?? null) === 1, true);
check('author is a Person', $decoded['author']['@type'] ?? null, 'Person');
check('datePublished is a date', (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($decoded['datePublished'] ?? '')), true);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
