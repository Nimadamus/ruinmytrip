<?php /** @var string $label @var array $d */ ?>
<div class="wrap" style="max-width:560px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <h1 style="margin:0 0 10px">Watch this for me</h1>
    <p><?= e($label) ?>. One email when it changes at the official source, nothing else. You can stop it from any email.</p>
    <form method="post" action="<?= e(url('updates/watch')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="topic" value="<?= e((string) $d['topic']) ?>">
      <input type="hidden" name="city" value="<?= e((string) $d['city']) ?>">
      <input type="hidden" name="source" value="<?= e((string) $d['source']) ?>">
      <button class="btn btn-accent">Watch it</button>
    </form>
  </div></section>
</div>
