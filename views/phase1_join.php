<?php
/** GET /checklist/join: the Phase 1 signup prompt as a page (no JavaScript). @var array $d */
$qjReturn = '/checklist/claim';
?>
<div class="wrap p1-page"><div class="card p1-join-page">
  <h1>Save your trip checklist</h1>
  <p class="p1-join-copy">Create a free account to save this warning to your trip checklist and get notified if new traps are reported for <?= e((string) $d['name']) ?>.</p>
  <form method="post" action="<?= e(url('register')) ?>"><?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($qjReturn) ?>">
    <?php include __DIR__ . '/auth/_quick_join_fields.php'; ?>
    <div style="margin-top:12px"><button class="btn btn-primary btn-block" type="submit" data-cta="p1_join">Create free account and save</button></div>
  </form>
  <p class="muted" style="margin-top:16px">Already a member? <a href="<?= e(url('login?return=' . rawurlencode($qjReturn))) ?>">Log in and save</a>
    · <a href="<?= e(url('d/' . $d['slug'])) ?>">Back to <?= e((string) $d['name']) ?></a></p>
</div></div>
