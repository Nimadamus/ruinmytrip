<?php /** @var array $rows @var string $q */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Places</p>
  <h1 style="margin:.2rem 0 .4rem">Places</h1>
  <p class="muted" style="margin:0 0 16px">
    <?= count($rows) ?> active <?= count($rows) === 1 ? 'place' : 'places' ?>.
    "Filled" counts the eight fields a place page can show: address, coordinates, phone, website,
    price, category, hours and a photo. It is a checklist, not a score to chase — a beach has no
    phone number and never will.
  </p>

  <form method="get" action="<?= e(url('admin/places')) ?>" style="display:flex;gap:8px;margin:0 0 18px;max-width:520px">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, slug or city" style="flex:1">
    <button class="btn btn-ghost">Filter</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= e(url('admin/places')) ?>">Clear</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <p class="muted">Nothing matches that.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.93rem">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid #e9e9ee">
            <th style="padding:8px 10px">Place</th>
            <th style="padding:8px 10px">Where</th>
            <th style="padding:8px 10px">Type</th>
            <th style="padding:8px 10px">Filled</th>
            <th style="padding:8px 10px">Source</th>
            <th style="padding:8px 10px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr style="border-bottom:1px solid #f1f1f5">
              <td style="padding:8px 10px"><a href="<?= e(url('p/'.$r['slug'])) ?>"><?= e($r['name']) ?></a></td>
              <td style="padding:8px 10px" class="muted"><?= e($r['dest_name']) ?>, <?= e($r['dest_country']) ?></td>
              <td style="padding:8px 10px" class="muted"><?= e(rmt_place_type_label((string) $r['type'])) ?></td>
              <td style="padding:8px 10px">
                <span style="display:inline-block;width:72px;height:7px;background:#e9e9ee;border-radius:99px;overflow:hidden;vertical-align:middle">
                  <span style="display:block;height:100%;width:<?= (int) $r['completeness'] ?>%;background:var(--ink)"></span>
                </span>
                <span class="muted" style="margin-left:6px"><?= (int) $r['completeness'] ?>%</span>
              </td>
              <td style="padding:8px 10px" class="muted">
                <?= $r['data_source'] ? e((string) $r['data_source']) : '<span class="hint">none</span>' ?>
                <?php if (!empty($r['data_checked_at'])): ?>
                  <span class="hint"><?= e(substr((string) $r['data_checked_at'], 0, 10)) ?></span>
                <?php endif; ?>
              </td>
              <td style="padding:8px 10px"><a class="btn btn-ghost" href="<?= e(url('admin/place/'.(int)$r['id'])) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
