<?php /** @var array $rows @var ?array $me @var array $dests */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Who's going</p>
  <h1>Who's going</h1>
  <div class="callout"><b>Destination + date range only.</b> RuinMyTrip never shows precise or real-time location. Share your plans only if you choose to, and control who sees them.</div>

  <?php if ($me): ?>
    <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('matches')) ?>">See who overlaps your dates</a></p>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <?php $current = null; $lockDestId = 0; include __DIR__.'/_going_form.php'; ?>
    </div></div>
  <?php else: ?>
    <p style="margin:16px 0"><a class="btn btn-accent" href="<?= e(url('register')) ?>">Join free to share dates</a></p>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>Nobody's shared public travel plans yet.</h3>
      <p class="muted" style="margin:0">When a traveler shares where they're headed, you'll see the destination and date range here — never a precise location.</p>
    </div>
  <?php else: ?>
    <div class="grid g-2" style="padding:14px 0 50px">
      <?php foreach ($rows as $r): ?>
        <div class="card"><div class="card-body" style="display:flex;gap:14px;align-items:center">
          <img class="avatar" style="width:48px;height:48px" src="<?= e(avatar_url($r['avatar_url']??null)) ?>" alt="">
          <div>
            <b><a href="<?= e(url('u/'.$r['username'])) ?>">@<?= e($r['username']) ?></a></b>
            <p class="muted" style="margin:.1rem 0 0">Heading to <a href="<?= e(url('d/'.$r['dest_slug'])) ?>"><?= e($r['dest_name']) ?></a></p>
            <p class="hint" style="margin:.1rem 0 0"><?= e(date('M j', strtotime((string)$r['date_from']))) ?> – <?= e(date('M j, Y', strtotime((string)$r['date_to']))) ?></p>
          </div>
        </div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
