<?php
/**
 * One warning in the moderation queue, with every decision available inline.
 *
 * Reject / request-revision / dispute require a note — the controller enforces it, and the field
 * sits right next to those buttons so the requirement reads as part of the action rather than as
 * an error message afterwards.
 *
 * @var array  $w
 * @var string $returnTo
 */
$returnTo = $returnTo ?? '/admin/warnings';
$sev = (int) $w['severity'];
?>
<div class="warn-card<?= $sev >= 3 ? ' s' . $sev : '' ?>">
  <div class="warn-meta">
    <span class="sev <?= e(rmt_severity_class($sev)) ?>"><?= e(rmt_severity_label($sev)) ?></span>
    <span class="chip chip-cat"><?= e(rmt_warning_category_label($w['category'])) ?></span>
    <span class="chip chip-muted"><?= e(str_replace('_', ' ', (string) $w['status'])) ?></span>
    <span class="trust trust-<?= e((string) $w['verification']) ?>"><?= e(ucfirst((string) $w['verification'])) ?></span>
    <a href="<?= e(url('d/' . $w['dest_slug'])) ?>"><?= e($w['dest_name']) ?></a>
    <span>· @<?= e((string) $w['username']) ?></span>
    <span>· submitted <?= e(ago((string) $w['created_at'])) ?></span>
    <?php if (!empty($w['date_experienced'])): ?><span>· experienced <?= e(rmt_experienced_label($w['date_experienced'])) ?></span><?php endif; ?>
  </div>

  <h3><a href="<?= e(url(ltrim(rmt_warning_path($w), '/'))) ?>"><?= e($w['title']) ?></a></h3>
  <p class="warn-body"><?= e(mb_strimwidth(strip_tags((string) $w['body']), 0, 500, '…')) ?></p>

  <?php if (!empty($w['provider_name'])): ?>
    <p class="hint"><b>Names a business:</b> <?= e($w['provider_name']) ?>
      <?php if (!empty($w['provider_type'])): ?>(<?= e(RMT_PROVIDER_TYPES[$w['provider_type']] ?? '') ?>)<?php endif; ?>
      — check it reads as a first-hand account, not an accusation.</p>
  <?php endif; ?>
  <?php if (empty($w['attested'])): ?>
    <p class="hint" style="color:#b91c1c"><b>Not attested.</b> The submitter did not confirm this was their own experience.</p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('admin/warnings/' . (int) $w['id'] . '/moderate')) ?>"
        style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($returnTo) ?>">
    <input name="note" placeholder="Note (required to reject, revise or dispute)" maxlength="1000"
           style="flex:1;min-width:240px;padding:.4rem .7rem;border:1px solid var(--line);border-radius:10px">
    <?php if ($w['status'] !== 'approved'): ?>
      <button class="btn btn-primary btn-sm" name="action" value="approve">Approve</button>
    <?php endif; ?>
    <button class="btn btn-ghost btn-sm" name="action" value="revise">Ask for revision</button>
    <button class="btn btn-ghost btn-sm" name="action" value="reject" data-confirm="Reject this warning?">Reject</button>
    <?php if ($w['status'] === 'approved'): ?>
      <button class="btn btn-ghost btn-sm" name="action" value="requeue">Back to queue</button>
      <?php if ($w['verification'] !== 'verified'): ?>
        <button class="btn btn-ghost btn-sm" name="action" value="verify" data-confirm="Mark verified? Only do this with independent corroboration.">Verify</button>
      <?php else: ?>
        <button class="btn btn-ghost btn-sm" name="action" value="unverify">Remove verification</button>
      <?php endif; ?>
      <button class="btn btn-ghost btn-sm" name="action" value="dispute">Mark disputed</button>
      <button class="btn btn-ghost btn-sm" name="action" value="<?= (int) $w['featured'] ? 'unfeature' : 'feature' ?>">
        <?= (int) $w['featured'] ? 'Unfeature' : 'Feature' ?>
      </button>
    <?php endif; ?>
  </form>
</div>
