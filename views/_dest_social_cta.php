<?php
/**
 * The module that turns a search visitor into a traveler on this site.
 *
 * @var string      $dsSlug    destination slug
 * @var string      $dsName    destination name
 * @var int|null    $dsId      destination id, when the caller knows it
 * @var bool|null   $dsCompact true on a place page, where it sits under the name and must stay short
 * @var int|null    $dsPlaceId the place being read, so "review it" can name it
 *
 * Why this exists. Every page that earns a search visit here is a place, a guide or an article, and
 * the reader arrives with a trip in mind and nothing on the page about the people. This module is
 * the site's one answer to that reader: who is going to this city, put your dates in right here,
 * and the things travelers do on this site (find a buddy, ask, warn people, say what went wrong).
 *
 * Every number is a COUNT of something real and is only printed above zero. The prompts are the
 * RuinMyTrip team's own questions, labelled as ours, and each one opens the city's composer; none of
 * them pretends to be a member. The date form is a GET to /plan with the city filled in, so the
 * dates somebody types here are never lost on the way to an account.
 */
$dsSlug = (string) ($dsSlug ?? '');
$dsName = (string) ($dsName ?? '');
if ($dsSlug === '' || $dsName === '') return;
$dsId = isset($dsId) ? (int) $dsId : 0;
if ($dsId < 1) $dsId = (int) (q_one('SELECT id FROM destinations WHERE slug = ?', [$dsSlug])['id'] ?? 0);
$dsCompact = !empty($dsCompact);
$dsPlaceId = isset($dsPlaceId) ? (int) $dsPlaceId : 0;
$dsWindow = function_exists('rmt_acq_window_near') ? rmt_acq_window_near($dsSlug) : null;
$dsBuddy = function_exists('rmt_buddy_landing_for_dest_slug') ? rmt_buddy_landing_for_dest_slug($dsSlug) : null;
$dsLive = function_exists('rmt_city_pulse') ? rmt_city_pulse($dsId, !$dsCompact) : ['going' => 0, 'buddies' => 0, 'questions' => 0, 'recent' => []];
$dsCity = url('d/' . $dsSlug);
$dsPlan = static fn(array $q): string => url('plan?' . http_build_query($q + ['d' => $dsSlug]));
$dsBits = [];
if ($dsLive['going'] > 0) $dsBits[] = $dsLive['going'] . ($dsLive['going'] === 1 ? ' traveler has' : ' travelers have') . ' upcoming dates';
if ($dsLive['buddies'] > 0) $dsBits[] = $dsLive['buddies'] . ' looking for a travel buddy';
if ($dsLive['questions'] > 0) $dsBits[] = $dsLive['questions'] . ($dsLive['questions'] === 1 ? ' conversation' : ' conversations');
?>
<section class="ds-cta card" style="margin:<?= $dsCompact ? '14px 0 22px' : '26px 0' ?>"><div class="card-body">
  <p style="margin:0 0 4px;font-size:1.08rem"><b>Who is going to <?= e($dsName) ?>?</b></p>
  <?php if ($dsBits): ?>
    <p class="ds-live"><?= e(implode(' · ', $dsBits)) ?></p>
  <?php endif; ?>
  <p class="hint" style="margin:0">
    <?php if ($dsWindow): ?>
      <?= e((string) $dsWindow['label']) ?> runs <?= e(date('j F', (int) strtotime((string) $dsWindow['from']))) ?> to
      <?= e(date('j F', (int) strtotime((string) $dsWindow['to']))) ?>.
    <?php endif; ?>
    <?= $dsBits ? 'Going soon? Add your dates and see whose overlap yours.'
                : 'Going soon? Add your dates. Anyone who posts overlapping dates later sees you, and you hear about it.' ?>
  </p>
  <form class="ds-dates" method="get" action="<?= e(url('plan')) ?>">
    <input type="hidden" name="d" value="<?= e($dsSlug) ?>"><input type="hidden" name="cta" value="cta_dates">
    <label>Arriving<input type="date" name="from" min="<?= e(date('Y-m-d')) ?>"
      value="<?= e($dsWindow ? (string) $dsWindow['from'] : '') ?>"></label>
    <label>Leaving<input type="date" name="to" min="<?= e(date('Y-m-d')) ?>"
      value="<?= e($dsWindow ? (string) $dsWindow['to'] : '') ?>"></label>
    <button class="btn btn-primary btn-sm" type="submit" data-cta="cta_dates" data-destination-id="<?= $dsId ?>">Find travelers on my dates</button>
  </form>
  <p style="margin:0;display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-ghost btn-sm" data-cta="cta_buddy" data-destination-id="<?= $dsId ?>" href="<?= e($dsPlan(['buddy' => '1', 'cta' => 'cta_buddy'])) ?>">Looking for a travel buddy?</a>
    <a class="btn btn-ghost btn-sm" data-cta="cta_ask" data-destination-id="<?= $dsId ?>" href="<?= e($dsCity . '#city-ask') ?>">Ask travelers a question</a>
    <a class="btn btn-ghost btn-sm" data-cta="cta_avoid" data-destination-id="<?= $dsId ?>" href="<?= e($dsCity . '?ask=avoid#city-ask') ?>">What should tourists avoid?</a>
    <?php if (!$dsCompact): ?>
      <a class="btn btn-ghost btn-sm" data-cta="cta_ruined" data-destination-id="<?= $dsId ?>" href="<?= e($dsCity . '?ask=ruined#city-ask') ?>">Post what went wrong on your trip</a>
    <?php endif; ?>
    <?php if ($dsPlaceId > 0): ?>
      <a class="btn btn-ghost btn-sm" data-cta="cta_review" data-review-cta="place" data-place-id="<?= $dsPlaceId ?>" href="<?= e(url('review/new?place=' . $dsPlaceId)) ?>">Been? Review it</a>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" data-cta="cta_travelers" data-destination-id="<?= $dsId ?>" href="<?= e(url('d/' . $dsSlug . '/travelers')) ?>">See who is going</a>
    <?php if ($dsBuddy && !$dsCompact): ?>
      <a class="btn btn-ghost btn-sm" data-cta="cta_buddy" href="<?= e(url(rmt_buddy_landing_path($dsBuddy['slug']))) ?>">Travel buddies in <?= e($dsBuddy['name']) ?></a>
    <?php endif; ?>
  </p>
  <?php if (!$dsCompact): ?>
    <?php if ($dsLive['recent']): ?>
      <p class="hint" style="margin:14px 0 4px">Latest in the <?= e($dsName) ?> community</p>
      <ul class="ds-q">
        <?php foreach ($dsLive['recent'] as $q): ?>
          <li><a href="<?= e(url('post/' . (int) $q['id'])) ?>"><?= e(excerpt((string) $q['body'], 120)) ?></a>
            <span class="hint">@<?= e((string) $q['username']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="hint" style="margin:14px 0 4px">Questions from the RuinMyTrip team. Answer one and start the <?= e($dsName) ?> conversation:</p>
      <ul class="ds-prompts">
        <?php foreach (rmt_city_prompts($dsName, 3) as $k => $text): ?>
          <li><a data-cta="cta_prompt" data-destination-id="<?= $dsId ?>" href="<?= e($dsCity . '?ask=' . $k . '#city-ask') ?>"><?= e($text) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>
  <p class="hint" style="margin:10px 0 0">Nobody sees your dates until you post them, and nobody can message you until you say yes.</p>
</div></section>
<?php unset($dsSlug, $dsName, $dsId, $dsWindow, $dsBuddy, $dsLive, $dsCity, $dsPlan, $dsBits, $dsCompact, $dsPlaceId); ?>
