<?php
/**
 * Travel partners in this city, on the city page (app/hubs.php): the open trips looking for
 * company, how many travelers are checked in right now, and where the warnings are.
 *
 * Renders nothing at all when there is nothing true to say: no open trip, nobody checked in and
 * no sourced warnings. A city page with none of those gains no empty box.
 *
 * @var array $d the destination row
 */
if (!function_exists('rmt_hub_live')) return;
$hubLive = rmt_hub_live((int) $d['id']);
$hubWarn = function_exists('rmt_city_scams') ? count(rmt_city_scams((string) $d['slug'])['items'] ?? []) : 0;
if ($hubLive['open_trip_count'] === 0 && $hubLive['checked_in'] === 0 && $hubWarn < RMT_HUB_MIN_WARNINGS) return;
$hubCity = (string) $d['name'];
?>
<div class="card hub-partners" id="travel-partners" style="margin-bottom:18px"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 6px">Travel partners in <?= e($hubCity) ?></h2>
  <?php $hubFacts = array_filter([
      $hubLive['open_trip_count'] > 0 ? $hubLive['open_trip_count'] . ($hubLive['open_trip_count'] === 1 ? ' trip is' : ' trips are') . ' looking for company' : '',
      $hubLive['checked_in'] > 0 ? $hubLive['checked_in'] . ($hubLive['checked_in'] === 1 ? ' traveler is' : ' travelers are') . ' checked in right now' : '',
    ]); ?>
  <?php if ($hubFacts): ?><p style="margin:0 0 10px"><?= e(implode(', and ', $hubFacts)) ?>.</p>
  <?php else: ?><p style="margin:0 0 10px">Nobody has posted a trip to <?= e($hubCity) ?> yet. Post your dates and the travelers going when you are will find you. Nobody can message you until you say yes.</p><?php endif; ?>

  <?php if ($hubLive['open_trips']): ?>
    <ul style="margin:0 0 10px;padding-left:1.1rem">
      <?php foreach ($hubLive['open_trips'] as $hubTrip): ?>
        <li style="margin:0 0 4px"><a href="<?= e(url('buddy/' . (int) $hubTrip['id'])) ?>"><?= e((string) $hubTrip['title']) ?></a>
          <span class="hint"><?= e(date('M j', strtotime((string) $hubTrip['date_from']))) ?> to <?= e(date('M j', strtotime((string) $hubTrip['date_to']))) ?><?php
            $hubVibe = function_exists('rmt_vibe_labels') ? rmt_vibe_labels($hubTrip['budget'] ?? null, $hubTrip['travel_pace'] ?? null) : [];
            if ($hubVibe): ?> &middot; <?= e(implode(', ', $hubVibe)) ?><?php endif; ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <p style="display:flex;gap:8px;flex-wrap:wrap;margin:0">
    <?php if ($hubLive['open_trip_count'] > 0): ?>
      <a class="btn btn-primary btn-sm" href="<?= e(url('buddies?dest=' . rawurlencode((string) $d['slug']))) ?>">See who is going to <?= e($hubCity) ?></a>
    <?php else: ?>
      <a class="btn btn-primary btn-sm" href="<?= e(is_logged_in() ? url('buddies/new?dest=' . rawurlencode((string) $d['slug'])) : url('plan?buddy=1&cta=cta_buddy&d=' . rawurlencode((string) $d['slug']))) ?>">Post your dates for <?= e($hubCity) ?></a>
    <?php endif; ?>
    <?php if ($hubWarn >= RMT_HUB_MIN_WARNINGS): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('warnings/' . $d['slug'])) ?>"><?= (int) $hubWarn ?> warnings for <?= e($hubCity) ?>, with sources</a>
    <?php endif; ?>
  </p>
</div></div>
