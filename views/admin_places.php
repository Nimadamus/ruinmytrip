<?php /** @var array $rows @var string $q @var array $coverage @var array $refusals @var array $stale */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Places</p>
  <h1 style="margin:.2rem 0 .4rem">Places</h1>
  <p class="muted" style="margin:0 0 16px">
    <?= count($rows) ?> active <?= count($rows) === 1 ? 'place' : 'places' ?>.
    "Filled" counts the eight fields a place page can show: address, coordinates, phone, website,
    price, category, hours and a photo. It is a checklist, not a score to chase — a beach has no
    phone number and never will.
  </p>

  <?php /* Coverage. Internal only: this is an operational view of what we hold, and none of it is
           a public claim about anything. A field at 0% is not a failure, it is a decision waiting
           to be made about whether that field is worth collecting for this kind of place. */ ?>
  <?php $t = max(1, (int) $coverage['total']); ?>
  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Coverage across <?= (int) $coverage['total'] ?> active places</h2>
    <p class="hint" style="margin:0 0 12px">
      Fully enriched <?= (int) $coverage['buckets']['full'] ?> ·
      partial <?= (int) $coverage['buckets']['partial'] ?> ·
      still thin <?= (int) $coverage['buckets']['thin'] ?>
    </p>
    <div class="grid g-2" style="gap:4px 24px">
      <?php foreach ($coverage['fields'] as $field => $n): $pct = (int) round($n * 100 / $t); ?>
        <div style="display:flex;align-items:center;gap:10px;margin:2px 0">
          <span class="muted" style="width:8.5rem;font-size:.9rem"><?= e(str_replace('_', ' ', $field)) ?></span>
          <span style="flex:1;height:7px;background:#e9e9ee;border-radius:99px;overflow:hidden">
            <span style="display:block;height:100%;width:<?= $pct ?>%;background:var(--ink)"></span>
          </span>
          <span class="muted" style="width:4.6rem;text-align:right;font-size:.88rem"><?= $n ?> · <?= $pct ?>%</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div></section>

  <?php /* The manual queue. Each row says WHY automatic enrichment would not touch it, because
           "no external match" and "the map says this is a bus stop" are different jobs. */ ?>
  <?php if ($refusals): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem">Needs a human (<?= count($refusals) ?>)</h2>
      <p class="hint" style="margin:0 0 10px">
        Automatic enrichment refused these on purpose. Refusing a doubtful match is the behaviour we
        want; each one just needs somebody to look.
      </p>
      <ul class="list-plain" style="margin:0">
        <?php foreach ($refusals as $r): ?>
          <li style="padding:5px 0;font-size:.92rem;border-bottom:1px solid #f1f1f5">
            <a href="<?= e(url('p/'.$r['slug'])) ?>"><?= e($r['name'] ?: $r['slug']) ?></a>
            <span class="muted"> — <?= e(str_replace('_', ' ', $r['reason'])) ?></span>
            <?php if ($r['detail']): ?><span class="hint"><?= e($r['detail']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></section>
  <?php endif; ?>

  <?php /* Freshness. Listed, never acted on automatically: a source going quiet is a reason to
           look, not a reason to remove a place travelers have reviewed. */ ?>
  <?php if ($stale): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem">Not re-checked in 6 months (<?= count($stale) ?>)</h2>
      <ul class="list-plain" style="margin:0">
        <?php foreach (array_slice($stale, 0, 20) as $r): ?>
          <li style="padding:4px 0;font-size:.92rem">
            <a href="<?= e(url('admin/place/'.(int)$r['id'])) ?>"><?= e($r['name']) ?></a>
            <span class="muted"><?= e((string) $r['dest_name']) ?> · <?= e((string) $r['data_source']) ?>
              · <?= e(substr((string) $r['data_checked_at'], 0, 10)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div></section>
  <?php endif; ?>

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
