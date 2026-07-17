<?php /** @var array $dests @var array $errors @var ?array $r */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Write a review</h1>
  <p class="muted">Be specific and fair. Real experiences help other travelers.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('review/new')) ?>"><?= csrf_field() ?>
    <?php include __DIR__ . '/_review_form.php'; ?>

    <label for="photos">Photos <span class="muted">(optional, up to 6)</span></label>
    <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    <p class="muted" style="margin:.3rem 0 0;font-size:.9rem">
      JPEG, PNG or WebP, up to 8MB each. Photos are resized and re-saved on upload, which removes
      camera metadata such as GPS location.
    </p>

    <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" name="action" value="publish">Publish review</button>
      <button class="btn btn-ghost" name="action" value="draft">Save as draft</button>
    </div>
    <p class="muted" style="margin-top:12px;font-size:.9rem">A draft is visible only to you until you publish it.</p>
  </form>
</div></div>
