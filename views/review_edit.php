<?php /** @var array $r @var array $dests @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit review</h1>
  <p class="muted">
    <?php if ($r['status'] === 'draft'): ?>This is a draft. Only you can see it.
    <?php elseif (in_array($r['status'], ['hidden','removed'], true)): ?>
      This review is currently <?= e($r['status']) ?> and stays that way until a moderator restores it.
    <?php else: ?>Changes go live as soon as you save.<?php endif; ?>
  </p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('review/'.(int)$r['id'].'/edit')) ?>"><?= csrf_field() ?>
    <?php include __DIR__ . '/_review_form.php'; ?>
    <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" name="action" value="publish">
        <?= $r['status'] === 'draft' ? 'Publish review' : 'Save changes' ?>
      </button>
      <?php if ($r['status'] !== 'draft'): ?>
        <button class="btn btn-ghost" name="action" value="draft">Unpublish to draft</button>
      <?php else: ?>
        <button class="btn btn-ghost" name="action" value="draft">Keep as draft</button>
      <?php endif; ?>
    </div>
  </form>
  <hr style="border:0;border-top:1px solid var(--line);margin:28px 0 18px">
  <form method="post" action="<?= e(url('review/'.(int)$r['id'].'/delete')) ?>"
        onsubmit="return confirm('Delete this review? It will be removed from your profile and from the destination page.');">
    <?= csrf_field() ?>
    <button class="btn btn-ghost" style="color:#b42318">Delete this review</button>
  </form>
</div></div>
