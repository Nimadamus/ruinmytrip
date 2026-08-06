<?php
/** @var array $rows @var array $dests */
$here = 'admin/affiliates';
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px">Affiliate links</h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="callout warn">
    <b>Nothing is live until you tick “Active”.</b> There are no seeded or placeholder links. Every link renders
    through one component that always prints the disclosure and always sets
    <code>rel="sponsored nofollow noopener"</code>, and every click is counted. Warnings, risk reports and FAQs
    are never gated behind any of this.
  </div>

  <h2 style="font-size:1.2rem;margin-top:24px">Existing links</h2>
  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>Label</th><th>Provider</th><th>Kind</th><th>Destination</th><th>Active</th><th>Clicks</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e((string) $r['label']) ?><br><span class="hint">/go/<?= e((string) $r['slug']) ?></span></td>
          <td><?= e((string) $r['provider']) ?></td>
          <td><?= e(RMT_AFFILIATE_KINDS[$r['kind']] ?? (string) $r['kind']) ?></td>
          <td><?= e((string) ($r['dest_name'] ?? 'Any')) ?></td>
          <td><?= (int) $r['active'] ? 'Yes' : 'No' ?></td>
          <td><?= (int) $r['click_count'] ?></td>
          <td>
            <form method="post" action="<?= e(url('admin/affiliate/' . (int) $r['id'] . '/delete')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn-ghost btn-sm" data-confirm="Delete this affiliate link?">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted">No affiliate links. The blocks stay hidden until you add one.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <form method="post" action="<?= e(url('admin/affiliate')) ?>" class="form-card form-wide" style="max-width:900px">
    <?= csrf_field() ?>
    <h2 style="font-size:1.15rem;margin-top:0">Add a link</h2>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <p style="flex:1;min-width:200px"><label for="af-slug">Slug</label>
        <input id="af-slug" name="slug" required maxlength="80" style="width:100%" placeholder="paris-hotels"></p>
      <p style="flex:1;min-width:200px"><label for="af-prov">Provider</label>
        <input id="af-prov" name="provider" maxlength="120" style="width:100%" placeholder="Booking.com"></p>
      <p style="flex:1;min-width:200px"><label for="af-kind">Kind</label>
        <select id="af-kind" name="kind" style="width:100%">
          <?php foreach (RMT_AFFILIATE_KINDS as $k => $lab): ?><option value="<?= e($k) ?>"><?= e($lab) ?></option><?php endforeach; ?>
        </select></p>
    </div>
    <p><label for="af-label">Label shown to readers</label>
      <input id="af-label" name="label" required maxlength="160" style="width:100%" placeholder="Compare hotels in central Paris"></p>
    <p><label for="af-url">Destination URL (https only)</label>
      <input id="af-url" name="target_url" required type="url" style="width:100%" placeholder="https://..."></p>
    <p><label for="af-blurb">One-line context</label>
      <input id="af-blurb" name="blurb" maxlength="300" style="width:100%"></p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <p style="flex:1;min-width:220px"><label for="af-dest">Destination</label>
        <select id="af-dest" name="destination_id" style="width:100%">
          <option value="">Any destination</option>
          <?php foreach ($dests as $dd): ?><option value="<?= (int) $dd['id'] ?>"><?= e($dd['name']) ?></option><?php endforeach; ?>
        </select></p>
      <p style="width:110px"><label for="af-sort">Sort</label>
        <input id="af-sort" name="sort" type="number" value="0" style="width:100%"></p>
      <p><label style="font-weight:400"><input type="checkbox" name="active" value="1"> Active</label></p>
    </div>
    <button class="btn btn-primary" type="submit">Add link</button>
  </form>
  <div style="height:50px"></div>
</div>
