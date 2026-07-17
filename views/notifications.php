<?php /** @var array $items */ ?>
<div class="wrap" style="max-width:680px;min-height:50vh">
  <h1 style="margin-top:24px">Notifications</h1>
  <?php if(!$items):?><p class="muted">Nothing yet. Follow travelers and join meetups to see activity here.</p><?php endif;?>
  <ul class="list-plain">
    <?php foreach ($items as $n): ?>
      <li class="card" style="margin-bottom:8px"><div class="card-body" style="padding:12px 16px">
        <?php if ($n['type']==='follow'): ?>
          <b>@<?= e($n['actor']) ?></b> started following you.
        <?php else: ?>
          <b><?= e($n['type']) ?></b> from @<?= e($n['actor']) ?>
        <?php endif; ?>
        <span class="hint"> · <?= e(ago($n['created_at'])) ?></span>
      </div></li>
    <?php endforeach; ?>
  </ul>
  <div style="height:40px"></div>
</div>
