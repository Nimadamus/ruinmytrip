<?php
/** @var array $rows */
$here = 'admin/responses';
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Business responses</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="callout">
    <b>Before approving.</b> Verify the sender actually represents the business — an address at the
    business's own domain, or a call to the number on its public website. A response is published at the same
    prominence as the warning, so an impostor response is as damaging as a fake warning. Approving a response
    does not hide or alter the original report.
  </div>

  <?php if (!$rows): ?><p class="muted">No responses have been filed.</p><?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <div class="card" style="margin-bottom:14px"><div class="card-body">
      <p style="margin:0">
        <b><?= e($r['responder_name']) ?></b>
        <?php if (!empty($r['responder_role'])): ?><span class="muted">· <?= e($r['responder_role']) ?></span><?php endif; ?>
        <span class="chip <?= $r['status'] === 'pending' ? 'chip-muted' : '' ?>"><?= e($r['status']) ?></span>
      </p>
      <p class="hint" style="margin:.2rem 0">
        Contact: <?= e((string) $r['contact_email']) ?> · filed <?= e(ago((string) $r['created_at'])) ?>
      </p>
      <p style="margin:.3rem 0"><b>Responding to:</b>
        <a href="<?= e(url('w/' . (int) $r['warning_id'] . '/' . $r['warning_slug'])) ?>"><?= e((string) $r['warning_title']) ?></a>
        <span class="muted">(<?= e((string) $r['dest_name']) ?>)</span></p>
      <div style="white-space:pre-wrap;margin:.6rem 0"><?= nl2br(e((string) $r['body'])) ?></div>

      <form method="post" action="<?= e(url('admin/response/' . (int) $r['id'])) ?>" style="display:flex;gap:8px">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-sm" name="action" value="approve"
                data-confirm="Publish this response? Confirm the sender is genuinely from this business first.">Approve &amp; publish</button>
        <button class="btn btn-ghost btn-sm" name="action" value="reject">Reject</button>
      </form>
    </div></div>
  <?php endforeach; ?>
  <div style="height:50px"></div>
</div>
