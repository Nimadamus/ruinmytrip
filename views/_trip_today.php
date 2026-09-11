<?php
/**
 * The trip while it is happening.
 *
 * A trip page written for planning is the wrong page to open on the third morning in Lisbon. What
 * somebody wants then is one screen: what is on today, where, and who else is coming. Everything
 * else on the page is still there, underneath, in the order it was.
 *
 * Shown only during the trip, and only when there is something true to put in it. A "Today" heading
 * over an empty box is worse than no heading.
 *
 * @var array  $t
 * @var array  $planDays   from rmt_activities_by_day()
 * @var string $phase
 * @var bool   $canEdit
 * @var ?array $me
 */
if (($phase ?? '') !== 'current') return;

$rmtToday = date('Y-m-d');
$rmtTomorrow = date('Y-m-d', strtotime('+1 day'));
$rmtOnToday = [];
$rmtOnTomorrow = [];
foreach (($planDays ?? []) as $rmtDay) {
    $key = (string) ($rmtDay['day'] ?? '');
    if ($key === $rmtToday)    $rmtOnToday = $rmtDay['items'];
    if ($key === $rmtTomorrow) $rmtOnTomorrow = $rmtDay['items'];
}
if (!$rmtOnToday && !$rmtOnTomorrow) return;

/** One line of the day: the time, what it is, and where, with nothing else competing. */
$rmtLine = static function (array $a): void { ?>
  <li>
    <?php if (!empty($a['start_time'])): ?>
      <span class="today-time"><?= e((string) $a['start_time']) ?></span>
    <?php else: ?>
      <span class="today-time today-time-any">any time</span>
    <?php endif; ?>
    <span class="today-what">
      <a href="<?= e(url('activity/'.(int) $a['id'])) ?>"><b><?= e((string) $a['title']) ?></b></a>
      <?php
        $bits = [];
        if (!empty($a['place_name'])) {
            $bits[] = '<a href="' . e(url('p/'.$a['place_slug'])) . '">' . e((string) $a['place_name']) . '</a>';
        } elseif (!empty($a['location_text'])) {
            $bits[] = e((string) $a['location_text']);
        }
        if ((int) ($a['going_count'] ?? 0) > 0) {
            $n = (int) $a['going_count'];
            $bits[] = $n . ($n === 1 ? ' other person coming' : ' others coming');
        }
      ?>
      <?php if ($bits): ?><span class="hint"><?= implode(' &middot; ', $bits) ?></span><?php endif; ?>
    </span>
  </li>
<?php }; ?>

<section class="today-card">
  <div class="today-head">
    <h2>Today</h2>
    <span class="hint"><?= e(date('l j F')) ?></span>
  </div>

  <?php if ($rmtOnToday): ?>
    <ul class="today-list"><?php foreach ($rmtOnToday as $rmtA) $rmtLine($rmtA); ?></ul>
  <?php else: ?>
    <p class="hint" style="margin:0">Nothing planned for today.</p>
  <?php endif; ?>

  <?php if ($rmtOnTomorrow): ?>
    <?php /* Tomorrow, not the whole rest of the week. One day ahead is what somebody standing in a
             street actually needs; the full itinerary is further down the page. */ ?>
    <div class="today-next">
      <h3>Tomorrow</h3>
      <ul class="today-list"><?php foreach ($rmtOnTomorrow as $rmtA) $rmtLine($rmtA); ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($canEdit ?? false): ?>
    <p class="today-acts">
      <a class="btn btn-ghost btn-sm" href="#plan">Change the plan</a>
      <?php if (!empty($t['destination_id'])): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('d/'.$t['dest_slug'].'/travelers')) ?>">Who else is here</a>
      <?php endif; ?>
    </p>
  <?php endif; ?>
</section>
