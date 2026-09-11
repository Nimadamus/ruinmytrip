<?php /** @var array $rows @var ?array $me @var array $dests @var array $cities @var int $filterDest @var string $filterMonth @var array $months */
$rmt_filtered = $filterDest > 0 || $filterMonth !== '';
$rmt_city_name = '';
foreach ($dests as $dd) if ((int) $dd['id'] === $filterDest) $rmt_city_name = (string) $dd['name'];
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Who's going</p>
  <h1 style="margin-bottom:.2rem">Who's going where</h1>
  <p class="muted" style="max-width:62ch">Travelers post the city and the dates they will be there.
    Destination and date range only, never a precise or live location.</p>

  <?php /* The board leads. This page used to open with a form and three explainer cards, so the one
           thing it exists to show, the people, was four screens down: somebody searching "who is
           going to Lisbon in March" arrived and was asked to fill something in first. */ ?>
  <form class="board-filters" method="get" action="<?= e(url('going')) ?>">
    <label class="sr-only" for="city">City</label>
    <select id="city" name="city">
      <option value="">Any city</option>
      <?php foreach ($dests as $dd): ?>
        <option value="<?= (int) $dd['id'] ?>"<?= $filterDest === (int) $dd['id'] ? ' selected' : '' ?>>
          <?= e($dd['name']) ?><?= !empty($dd['country']) ? ', ' . e((string) $dd['country']) : '' ?></option>
      <?php endforeach; ?>
    </select>
    <label class="sr-only" for="month">Month</label>
    <select id="month" name="month">
      <option value="">Any time</option>
      <?php foreach ($months as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $filterMonth === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm">Show travelers</button>
    <?php if ($rmt_filtered): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('going')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty-cta" style="margin:14px 0 40px">
      <?php if ($rmt_filtered): ?>
        <h3>Nobody has posted dates for that yet.</h3>
        <p class="muted" style="margin:0">You would be the first, which is the one people find.
          <?php if ($rmt_city_name !== ''): ?>Nothing here is hidden from you: this is everything
            posted publicly for <?= e($rmt_city_name) ?>.<?php endif; ?></p>
      <?php else: ?>
        <h3>Nobody has posted public travel plans yet.</h3>
        <p class="muted" style="margin:0">Whoever goes first is the traveler everybody arriving after
          them sees. Post the city and the range; nothing finer is ever shown.</p>
      <?php endif; ?>
      <p style="margin:14px 0 0">
        <a class="btn btn-primary btn-sm" href="<?= e($me ? url('trip/new') : url('register?return=%2Fgoing')) ?>">Post your dates</a>
      </p>
    </div>
  <?php else: ?>
    <p class="hint" style="margin:2px 0 14px"><?= count($rows) ?>
      <?= count($rows) === 1 ? 'traveler' : 'travelers' ?><?php
        if ($rmt_city_name !== ''): ?> in <?= e($rmt_city_name) ?><?php endif; ?><?php
        if ($filterMonth !== ''): ?> in <?= e($months[$filterMonth] ?? '') ?><?php endif; ?>.
      <?php if ($me): ?><a href="<?= e(url('matches')) ?>">See who overlaps your own dates</a>.<?php endif; ?></p>
    <div class="grid g-2" style="padding:0 0 40px">
      <?php foreach ($rows as $r): ?>
        <div class="card"><div class="card-body" style="display:flex;gap:14px;align-items:center">
          <img class="avatar" style="width:48px;height:48px" src="<?= e(avatar_url($r['avatar_url']??null)) ?>" alt="">
          <div style="min-width:0">
            <b><a href="<?= e(url('u/'.$r['username'])) ?>">@<?= e($r['username']) ?></a></b>
            <p class="muted" style="margin:.1rem 0 0">Heading to <a href="<?= e(url('d/'.$r['dest_slug'].'/travelers')) ?>"><?= e($r['dest_name']) ?></a></p>
            <p class="hint" style="margin:.1rem 0 0"><?= e(date('M j', strtotime((string)$r['date_from']))) ?> to <?= e(date('M j, Y', strtotime((string)$r['date_to']))) ?></p>
          </div>
          <?php if ($me && (int) $r['user_id'] !== (int) $me['id']): ?>
            <a class="btn btn-ghost btn-sm" style="margin-left:auto" href="<?= e(url('messages/'.$r['username'])) ?>">Message</a>
          <?php endif; ?>
        </div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($cities)): ?>
    <h2 style="margin:8px 0 10px">Browse by city</h2>
    <div class="tag-list" style="margin-bottom:26px">
      <?php foreach ($cities as $c): $n = (int)$c['going_count'] + (int)$c['meetup_count']; ?>
        <a class="chip" href="<?= e(url('d/'.$c['slug'].'/travelers')) ?>"><?= e($c['name']) ?><?php
          if ($n): ?> <span class="hint"><?= $n ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php /* Posting your own dates comes after the board: somebody who has just seen four people in
           the city they are thinking about has a reason to, and somebody who has not seen anybody
           is being asked to fill in a form for a stranger. */ ?>
  <div class="card" style="margin:0 0 20px"><div class="card-body">
    <?php if ($me): ?>
      <?php $current = null; $lockDestId = 0; include __DIR__.'/_going_form.php'; ?>
    <?php else: ?>
      <b>Post your own dates</b>
      <p class="muted" style="margin:.3rem 0 12px">Free, and destination plus date range only. You
        choose who can see each trip: anyone, the people who follow you, or nobody but you.</p>
      <a class="btn btn-accent" href="<?= e(url('register?return=%2Fgoing')) ?>">Join free to share dates</a>
    <?php endif; ?>
  </div></div>

  <div class="callout" style="margin-bottom:40px"><b>Destination and date range only.</b>
    RuinMyTrip never shows precise or real-time location, and every trip carries its own visibility.</div>
</div>
