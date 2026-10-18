<?php /** @var array $dests @var array $errors @var ?array $r @var bool $guest @var bool $restored */ $guest = !empty($guest); ?>
<div class="wrap"><div class="form-card form-wide">
  <?php if (!empty($boundDest)): /* From a city's "Been to X?" stars (2026-10-17): the question, then
     straight to the stars and the box, which have to be on a phone's first screen. */ ?>
  <h1>How was <?= e((string) $boundDest['name']) ?>?</h1>
  <?php if ($guest): ?><p class="hint">No account needed to start. After you post, one quick step makes your free account. Nothing you type is lost.</p><?php endif; ?>
  <?php else: ?>
  <h1>Write a review</h1>
  <p class="muted">Be specific and fair. Real experiences help other travelers.</p>
  <?php if ($guest): ?><p class="hint" style="margin-top:-4px">No account needed to start. Stars and a few sentences is all it takes; after you post, one quick step makes your free account. Nothing you type is lost.</p><?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($restored)): ?><p class="review-saved-note" role="status">We kept the review you wrote. Check it and post it.</p><?php endif; ?>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <?php $draftKey = 'new-' . (int) ($boundPlace['id'] ?? 0) . '-' . (int) ($r['destination_id'] ?? 0); ?>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('review/new')) ?>"
        data-review-draft="<?= e($draftKey) ?>"><?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('review_new')) ?>">
    <?php if ($guest): /* A field people never see and scripts fill in (2026-10-11). Off screen, not display:none, and skipped by keyboard and screen readers. */ ?>
    <div class="hp-field" aria-hidden="true"><label>Leave this empty <input type="text" name="hp_note" value="" tabindex="-1" autocomplete="off"></label></div>
    <?php endif; ?>
    <?php include __DIR__ . '/_review_form.php'; ?>

    <?php if (!$guest): ?>
    <label for="photos">Photos <span class="muted">(optional, up to 6)</span></label>
    <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    <p class="muted" style="margin:.3rem 0 0;font-size:.9rem">
      JPEG, PNG or WebP, up to 8MB each. Photos are resized and re-saved on upload, which removes
      camera metadata such as GPS location.
    </p>

    <?php else: ?>
    <p class="muted" style="margin:.6rem 0 0;font-size:.9rem">You can add photos to your review once it is published.</p>
    <?php endif; ?>
    <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" name="action" value="publish"><?= $guest ? 'Post review' : 'Publish review' ?></button>
      <?php if (!$guest): ?><button class="btn btn-ghost" name="action" value="draft">Save as draft</button><?php endif; ?>
    </div>
    <?php if (!$guest): ?><p class="muted" style="margin-top:12px;font-size:.9rem">A draft is visible only to you until you publish it.</p><?php endif; ?>
  </form>
</div></div>
