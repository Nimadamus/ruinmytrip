<?php
/**
 * A short, practical paragraph on the trip type pages (/buddies/backpacking, /buddies/road-trip,
 * /buddies/cruise and the rest), so each one says something of its own instead of repeating the
 * /buddies page with a filter applied. Only claims about how this site works, or general advice a
 * reader can check, and links to the country pages that suit the trip type.
 *
 * @var string $buddyType a key of RMT_BUDDY_TYPES
 */
$bt = (string) ($buddyType ?? '');
$btCountries = [
    'backpacking' => ['thailand', 'vietnam', 'peru', 'colombia', 'morocco', 'mexico'],
    'road_trip'   => ['iceland', 'portugal', 'spain', 'croatia', 'italy', 'mexico'],
    'adventure'   => ['peru', 'iceland', 'colombia', 'morocco', 'vietnam'],
    'resort'      => ['mexico', 'bali', 'thailand', 'greece'],
    'festival'    => ['spain', 'thailand', 'mexico', 'japan'],
];
$btText = [
    'backpacking' => 'Backpackers rarely fix exact dates, so tick "Flexible dates, a week either side" and search a whole country rather than one city. Post your route as a trip with a date range, and anyone whose range overlaps yours will see it. Meet somebody new in a public place before agreeing to share a room or a bus.',
    'road_trip'   => 'A shared road trip splits fuel and the rental, but settle the details before you book: every person who drives should be named on the rental agreement, because an unnamed driver is usually not insured. Agree on the route, the budget and who pays what in writing in your messages first.',
    'cruise'      => 'Add your cruise line, ship and sailing date and you will see other people on the same sailing, not just the same port. Shore excursions and dinner tables are the usual reasons to connect before boarding.',
    'adventure'   => 'For treks and tours, compare operators and book with a licensed one before you look for company, then post the dates you have. Somebody on the same departure is worth more than somebody in the same city.',
    'resort'      => 'Resort trips overlap on fixed dates, so post yours exactly and look for people at the same place for dinners, day trips and excursions.',
    'festival'    => 'Festival dates are fixed, so post them exactly. Some events also have their own page here listing who is going; see the upcoming events.',
    'business'    => 'Travelling for work? Post the city and dates and see who else is there that week for a meal after the meetings.',
    'trip'        => 'Post where you are going and when, and anyone heading to the same place on overlapping dates will find you.',
];
if (!isset($btText[$bt])) return;
?>
<div class="buddy-type-intro" style="max-width:62em;margin:0 0 18px">
  <p style="margin:0 0 8px"><?= e($btText[$bt]) ?> Nobody can message you until you say yes.</p>
  <?php if (!empty($btCountries[$bt])): ?>
    <p class="hint" style="margin:0 0 6px">Country pages that suit this kind of trip:
      <?php foreach ($btCountries[$bt] as $i => $cs): $bl = function_exists('rmt_buddy_landing_path') ? rmt_buddy_landing_path($cs) : 'travel-buddies/' . $cs; ?>
        <?= $i ? ' · ' : '' ?><a href="<?= e(url(ltrim($bl, '/'))) ?>"><?= e(ucfirst($cs)) ?></a>
      <?php endforeach; ?></p>
  <?php endif; ?>
  <p class="hint" style="margin:0">Before you meet anyone, read <a href="<?= e(url('blog/how-to-find-a-travel-buddy-safely')) ?>">how to find a travel buddy safely</a> and our <a href="<?= e(url('safety')) ?>">safety page</a>.</p>
</div>
