<?php /** @var array $rows */ $me = current_user(); ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Who's going</p>
  <h1>Who's going</h1>
  <div class="callout"><b>Destination + date range only.</b> RuinMyTrip never shows precise or real-time location. Share your plans only if you choose to, and control who sees them in <a href="<?= e($me?url('settings'):url('register')) ?>">settings</a>.</div>
  <div class="grid g-2" style="padding:14px 0 50px">
    <?php foreach ($rows as $r): ?>
      <div class="card"><div class="card-body" style="display:flex;gap:14px;align-items:center">
        <img class="avatar" style="width:48px;height:48px" src="<?= e($r['avatar_url']??'') ?>" alt="">
        <div>
          <b><a href="<?= e(url('u/'.$r['username'])) ?>">@<?= e($r['username']) ?></a></b>
          <p class="muted" style="margin:.1rem 0 0">Heading to <a href="<?= e(url('d/'.$r['dest_slug'])) ?>"><?= e($r['dest_name']) ?></a></p>
          <p class="hint" style="margin:.1rem 0 0"><?= e(date('M j', strtotime((string)$r['date_from']))) ?> – <?= e(date('M j, Y', strtotime((string)$r['date_to']))) ?></p>
        </div>
      </div></div>
    <?php endforeach; ?>
    <?php if(!$rows):?><p class="muted">No public travel plans listed yet.</p><?php endif;?>
  </div>
</div>
