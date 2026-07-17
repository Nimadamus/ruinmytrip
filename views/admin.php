<?php /** @var array $reports @var array $stats */ ?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Moderation dashboard</h1>
  <div class="grid g-4" style="margin:18px 0 30px">
    <?php foreach (['users'=>'Users','trips'=>'Trips','reviews'=>'Reviews','open_reports'=>'Open reports'] as $k=>$label): ?>
      <div class="card"><div class="card-body"><p class="eyebrow"><?= e($label) ?></p><b style="font-size:1.8rem"><?= (int)$stats[$k] ?></b></div></div>
    <?php endforeach; ?>
  </div>
  <h2>Open reports</h2>
  <?php if(!$reports):?><p class="muted">No open reports. Nice and quiet.</p><?php endif;?>
  <?php foreach ($reports as $r): ?>
    <div class="card" style="margin-bottom:10px"><div class="card-body" style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap">
      <div>
        <b><?= e($r['reason']) ?></b> · <span class="muted"><?= e($r['target_type']) ?> #<?= (int)$r['target_id'] ?></span><br>
        <span class="hint">by @<?= e($r['reporter']) ?> · <?= e(ago($r['created_at'])) ?></span>
        <?php if($r['details']):?><p style="margin:.4rem 0 0"><?= e($r['details']) ?></p><?php endif;?>
      </div>
      <form method="post" action="<?= e(url('admin/resolve')) ?>" style="display:flex;gap:8px;align-items:center"><?= csrf_field() ?>
        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-ghost btn-sm" name="action" value="dismiss">Dismiss</button>
        <button class="btn btn-accent btn-sm" name="action" value="hide" data-confirm="Hide this content?">Hide content</button>
      </form>
    </div></div>
  <?php endforeach; ?>
  <div style="height:40px"></div>
</div>
