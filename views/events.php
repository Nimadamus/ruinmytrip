<?php /** @var array $events @var array|null $me */ ?>
<div class="container" style="max-width:820px">
  <h1 style="margin:0 0 6px">Upcoming travel events</h1>
  <p class="muted" style="margin:0 0 4px">Events where a lot of people go to one city at the same
    time, and where "who else is here this week" is a question worth answering. Every date below is
    checked against the organiser or, where there is no organiser, against the calendar.</p>
  <p class="hint" style="margin:0 0 22px">This is not a list of everything happening in the world. It
    is the handful of windows this site is set up for, and it will stay short.</p>

  <?php if (!$events): ?>
    <p class="muted">No window is open at the moment. When one is, it appears here.</p>
  <?php endif; ?>

  <?php foreach ($events as $ev): ?>
    <section class="card" style="margin:0 0 16px"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 2px"><?= e((string) $ev['dates']) ?></p>
      <h2 style="margin:0 0 4px;font-size:1.15rem">
        <?= e((string) $ev['label']) ?> &middot;
        <a href="<?= e(url('d/' . $ev['slug'])) ?>"><?= e((string) $ev['city']) ?></a>
      </h2>
      <p class="muted" style="margin:0 0 10px"><?= e((string) $ev['why']) ?></p>
      <p style="margin:0;display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="<?= e($ev['trip_link']) ?>">Post your <?= e((string) $ev['city']) ?> dates</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $ev['slug'] . '/travelers')) ?>">See who is going</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $ev['slug'])) ?>">The community</a>
      </p>
    </div></section>
  <?php endforeach; ?>

  <p class="hint" style="margin:18px 0 0">Nothing here counts anybody. When travelers post dates
    inside one of these windows, they appear on that city's page and on your matches, and not before.</p>
</div>
