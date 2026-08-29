<?php /** @var array $rows */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Suggested places</p>
  <h1 style="margin:.2rem 0 .4rem">Suggested places</h1>
  <?php $pending = count(array_filter($rows, static fn($r) => $r['status'] === 'pending')); ?>
  <p class="muted" style="margin:0 0 6px"><?= $pending ?> waiting.</p>
  <p class="hint" style="margin:0 0 18px">
    Marking one "added" records the decision; it does not create the place. Adding it is a decision
    with a destination, a type and a dedupe check behind it, and that happens in the place editor.
    A one-click accept here would be exactly the unreviewed entity creation this queue exists to
    prevent.
  </p>
  <?php if (!$rows): ?>
    <p class="muted">Nothing suggested yet.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.93rem">
        <thead><tr style="text-align:left;border-bottom:1px solid #e9e9ee">
          <th style="padding:6px 10px 6px 0">Place</th><th style="padding:6px 8px">City</th>
          <th style="padding:6px 8px">Kind</th><th style="padding:6px 8px">Suggested by</th>
          <th style="padding:6px 8px">When</th><th style="padding:6px 8px">Status</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr style="border-bottom:1px solid #f1f1f5<?= $r['status'] === 'pending' ? '' : ';opacity:.6' ?>">
              <td style="padding:6px 10px 6px 0"><?= e((string) $r['name']) ?>
                <?php if (!empty($r['website_url'])): ?>
                  <a class="hint" href="<?= e((string) $r['website_url']) ?>" rel="nofollow noopener" target="_blank">site</a>
                <?php endif; ?></td>
              <td style="padding:6px 8px"><?= e((string) $r['city']) ?></td>
              <td style="padding:6px 8px" class="muted"><?= e(rmt_place_type_label((string) $r['type'])) ?></td>
              <td style="padding:6px 8px" class="muted"><?= $r['username'] ? '@' . e((string) $r['username']) : '&mdash;' ?></td>
              <td style="padding:6px 8px" class="muted"><?= e(substr((string) $r['created_at'], 0, 10)) ?></td>
              <td style="padding:6px 8px"><?= e((string) $r['status']) ?></td>
              <td style="padding:6px 8px">
                <?php if ($r['status'] === 'pending'): ?>
                  <form method="post" action="<?= e(url('admin/suggestions/resolve')) ?>" style="display:flex;gap:6px;margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-ghost" style="padding:3px 9px;font-size:.82rem" name="status" value="added">Added</button>
                    <button class="btn btn-ghost" style="padding:3px 9px;font-size:.82rem" name="status" value="duplicate">Duplicate</button>
                    <button class="btn btn-ghost" style="padding:3px 9px;font-size:.82rem" name="status" value="rejected">Reject</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
