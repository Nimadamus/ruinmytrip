<?php /** @var array $rows @var string $status @var int $pending */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Feedback and corrections</p>
  <h1 style="margin:.2rem 0 .4rem">Feedback and corrections</h1>
  <p class="hint" style="margin:0 0 6px;max-width:72ch">
    Corrections to place pages and messages about the site, in one queue.
    <b>Resolving an item here changes nothing about the place.</b> If a correction is right, make the
    change in the place editor; this queue only records that somebody told us and that a person
    looked.
  </p>
  <p class="hint" style="margin:0 0 20px">
    <?php foreach (RMT_FEEDBACK_STATUSES as $st): ?>
      <a href="<?= e(url('admin/feedback') . '?status=' . $st) ?>"<?= $st === $status ? ' style="font-weight:700"' : '' ?>><?= e(ucfirst($st)) ?></a><?php
        if ($st === 'pending' && $pending > 0): ?> (<?= (int) $pending ?>)<?php endif; ?><?= $st === 'duplicate' ? '' : ' · ' ?>
    <?php endforeach; ?>
  </p>

  <?php if (!$rows): ?>
    <p class="muted">Nothing <?= e($status) ?>.</p>
  <?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <section class="card" style="margin:0 0 14px"><div class="card-body">
      <p style="margin:0 0 4px">
        <b><?= e(rmt_feedback_kind_label((string) $r['kind'])) ?></b>
        <?php if (!empty($r['place_slug'])): ?>
          &middot; <a href="<?= e(url('p/'.$r['place_slug'])) ?>"><?= e((string) $r['place_name']) ?></a>
          <span class="muted"><?= e((string) $r['dest_name']) ?></span>
          &middot; <a href="<?= e(url('admin/place/'.(int) $r['place_id'])) ?>">Edit this place</a>
        <?php else: ?>
          <span class="muted">&middot; not about a place</span>
        <?php endif; ?>
      </p>
      <p class="hint" style="margin:0 0 8px">
        <?= e(substr((string) $r['created_at'], 0, 16)) ?>
        &middot; <?= !empty($r['reporter']) ? '@' . e((string) $r['reporter']) : 'not signed in' ?>
        <?php if (!empty($r['contact_email'])): ?>
          &middot; <a href="mailto:<?= e((string) $r['contact_email']) ?>"><?= e((string) $r['contact_email']) ?></a>
        <?php endif; ?>
      </p>

      <?php /* The message itself, in full. Deciding from a summary is how a real correction gets
               dismissed as noise. */ ?>
      <p style="margin:0 0 12px;white-space:pre-wrap"><?= e((string) $r['message']) ?></p>

      <?php if ($r['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('admin/feedback/resolve')) ?>"
              style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input type="text" name="note" maxlength="500" placeholder="What you did about it (optional)"
                 style="flex:1;min-width:220px">
          <button class="btn btn-primary btn-sm" name="status" value="resolved">Done</button>
          <button class="btn btn-ghost btn-sm" name="status" value="duplicate">Duplicate</button>
          <button class="btn btn-ghost btn-sm" name="status" value="rejected">Not a problem</button>
        </form>
      <?php else: ?>
        <p class="hint" style="margin:0">
          <?= e(ucfirst((string) $r['status'])) ?>
          <?php if (!empty($r['resolved_at'])): ?> <?= e(substr((string) $r['resolved_at'], 0, 16)) ?><?php endif; ?>
          <?php if (!empty($r['resolution_note'])): ?> &mdash; <?= e((string) $r['resolution_note']) ?><?php endif; ?>
        </p>
      <?php endif; ?>
    </div></section>
  <?php endforeach; ?>
  <div style="height:40px"></div>
</div>
