<?php /** @var string $msg */ ?>
<div class="wrap" style="text-align:center;padding:80px 20px;min-height:50vh">
  <p class="eyebrow">403</p>
  <h1>Not authorized</h1>
  <p class="muted"><?= e($msg) ?></p>
  <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url()) ?>">Back home</a> <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
</div>
