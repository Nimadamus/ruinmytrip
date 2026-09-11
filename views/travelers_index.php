<?php /** @var array $people @var ?array $me @var array $suggested @var array $cities
        @var array $find @var int $matchCount @var ?array $cityRow @var array $hereNow
        @var array $cityLocals @var array $allDests */
$rmt_day = static fn(?string $d): string => $d ? date('j M', strtotime($d)) : '';
$rmt_days = static fn(?string $a, ?string $b): string => rmt_card_date_range((string) $a, (string) $b);
$backTo = '/travelers' . ($cityRow ? '?city=' . (int) $cityRow['id'] : '');
?>
<section class="block"><div class="wrap">
  <div class="section-head">
    <div>
      <h1 style="margin-bottom:.2rem">Find travelers</h1>
      <p class="muted" style="max-width:62ch">Six ways to find somebody: whose dates land on yours,
        who is going where you are going, who is in a city today, who travels the way you do, who
        said yes to the same meetup, and who lives there.</p>
    </div>
    <?php if (!$me): ?>
      <a class="btn btn-accent btn-sm" href="<?= e(url('register?return=%2Ftravelers')) ?>">Join free</a>
    <?php endif; ?>
  </div>

  <?php /* Ask a city directly. Without this the page only works for somebody who has already
           posted a trip, which is nobody on their first visit. */ ?>
  <form class="board-filters" method="get" action="<?= e(url('travelers')) ?>">
    <label class="sr-only" for="city">City</label>
    <select id="city" name="city">
      <option value="">Pick a city</option>
      <?php foreach ($allDests as $dd): ?>
        <option value="<?= (int) $dd['id'] ?>"<?= $cityRow && (int) $cityRow['id'] === (int) $dd['id'] ? ' selected' : '' ?>>
          <?= e($dd['name']) ?><?= !empty($dd['country']) ? ', ' . e((string) $dd['country']) : '' ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm">Who is there</button>
    <?php if ($cityRow): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('d/'.$cityRow['slug'].'/travelers')) ?>">Everything about <?= e((string) $cityRow['name']) ?></a>
    <?php endif; ?>
  </form>

  <?php /* ---------------------------------------------------------------- a city, asked */ ?>
  <?php if ($cityRow): ?>
    <section class="find-block">
      <h2 class="find-h">In <?= e((string) $cityRow['name']) ?> right now</h2>
      <?php if ($hereNow): ?>
        <p class="hint">Their own published dates cover today. A city and a date range, never a
          precise or live location.</p>
        <?php foreach ($hereNow as $pp): ?>
          <?php $person = $pp; $because = 'Here until ' . $rmt_day($pp['date_to']);
                include __DIR__ . '/_person_card.php'; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="muted">Nobody has dates covering today in <?= e((string) $cityRow['name']) ?>.
          <a href="<?= e($me ? url('trip/new?destination='.(int) $cityRow['id']) : url('register?return='.rawurlencode($backTo))) ?>">Post yours</a>
          and you are the one the next person finds.</p>
      <?php endif; ?>
    </section>

    <?php if ($cityLocals): ?>
      <section class="find-block">
        <h2 class="find-h">Locals open to meeting travelers</h2>
        <p class="hint">People who live in <?= e((string) $cityRow['name']) ?> and ticked the box
          that says they are happy to answer a question or meet in public. Never anybody who did
          not.</p>
        <?php foreach ($cityLocals as $pp): ?>
          <?php $person = $pp;
                $because = 'Lives in ' . (string) $cityRow['name']
                         . ((int) ($pp['reviews'] ?? 0) > 0 ? ' · ' . (int) $pp['reviews'] . ' reviews' : '');
                include __DIR__ . '/_person_card.php'; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* ---------------------------------------------------------------- for a member */ ?>
  <?php if ($me): ?>
    <?php if (!empty($find['overlapping'])): ?>
      <section class="find-block">
        <h2 class="find-h">On your dates</h2>
        <p class="hint">Same city, same days. The reason this site exists.</p>
        <?php foreach ($find['overlapping'] as $pp): ?>
          <?php $person = $pp;
                $because = (string) $pp['dest_name'] . ' · '
                         . (int) $pp['overlap_days'] . ((int) $pp['overlap_days'] === 1 ? ' day' : ' days')
                         . ' with you, ' . $rmt_days($pp['overlap_from'], $pp['overlap_to']);
                include __DIR__ . '/_person_card.php'; ?>
        <?php endforeach; ?>
        <?php if ($matchCount > count($find['overlapping'])): ?>
          <p style="margin:10px 0 0"><a href="<?= e(url('matches')) ?>">All <?= (int) $matchCount ?> matches</a></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($find['same_city'])): ?>
      <section class="find-block">
        <h2 class="find-h">Going where you are going</h2>
        <p class="hint">Same city, different weeks. Still worth knowing: they have been planning
          the same trip.</p>
        <?php foreach ($find['same_city'] as $pp): ?>
          <?php $person = $pp;
                $because = (string) $pp['dest_name'] . ' · ' . $rmt_days($pp['date_from'], $pp['date_to']);
                include __DIR__ . '/_person_card.php'; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($find['meetup_peers'])): ?>
      <section class="find-block">
        <h2 class="find-h">Going to the same meetup</h2>
        <?php foreach ($find['meetup_peers'] as $pp): ?>
          <?php $person = $pp;
                $because = (string) $pp['meetup_title'] . ' · ' . date('D j M', strtotime((string) $pp['date_start']))
                         . ($pp['dest_name'] ? ' · ' . (string) $pp['dest_name'] : '');
                include __DIR__ . '/_person_card.php'; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($find['kindred'])): ?>
      <section class="find-block">
        <h2 class="find-h">Travels like you</h2>
        <p class="hint">Based on the cities you both saved and the travel style you both chose.
          Both are things people said about themselves, not guesses we made about them.</p>
        <?php foreach ($find['kindred'] as $pp): ?>
          <?php $person = $pp;
                $bits = [];
                if ((int) $pp['shared_cities'] > 0) $bits[] = (int) $pp['shared_cities'] . ' cities you both want';
                if (!empty($pp['same_style'])) $bits[] = 'travels the same way';
                $because = implode(' · ', $bits);
                include __DIR__ . '/_person_card.php'; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($suggested)): ?>
      <section class="find-block">
        <h2 class="find-h">Worth following</h2>
        <?php foreach ($suggested as $pp): ?>
          <?php $person = $pp; $because = (string) ($pp['reason'] ?? '');
                include __DIR__ . '/_person_card.php'; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  <?php else: ?>
    <div class="callout" style="margin:18px 0">
      <b>The good half of this page needs to know where you are going.</b>
      Post a city and a date range and it fills with people whose trip lands on top of yours.
      <a href="<?= e(url('register?return=%2Ftravelers')) ?>">Join free</a>, it takes a minute.
    </div>
  <?php endif; ?>

  <?php /* ---------------------------------------------------------------- browse */ ?>
  <h2 class="find-h" style="margin-top:30px">Every city with somebody in it</h2>
  <div class="tag-list" style="margin-bottom:26px">
    <?php foreach ($cities as $c):
        $n = (int)$c['going_count'] + (int)$c['meetup_count'] + (int)$c['talk_count']; ?>
      <a class="chip" href="<?= e(url('d/'.$c['slug'].'/travelers')) ?>"><?= e($c['name']) ?><?php
        if ($n): ?> <span class="hint"><?= $n ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </div>

  <h2 class="find-h">Everybody here</h2>
  <div class="grid g-2" style="padding-bottom:40px">
    <?php foreach ($people as $pp): ?>
      <div class="card"><div class="card-body" style="padding:14px 16px">
        <?php $person = $pp;
              $bits = [];
              if ((int) ($pp['reviews'] ?? 0) > 0) $bits[] = (int) $pp['reviews'] . ' reviews';
              if ((int) ($pp['trips'] ?? 0) > 0) $bits[] = (int) $pp['trips'] . ' trips';
              $because = implode(' · ', $bits);
              include __DIR__ . '/_person_card.php'; ?>
      </div></div>
    <?php endforeach; ?>
  </div>
</div></section>
