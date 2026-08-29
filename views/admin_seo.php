<?php /** @var array $groups @var array $sitemap @var int $totalUrls @var ?string $generated */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / SEO readiness</p>
  <h1 style="margin:.2rem 0 .4rem">SEO readiness</h1>
  <p class="hint" style="margin:0 0 20px;max-width:70ch">
    What is in the index, what is not, and why not &mdash; every row decided by
    <code>rmt_indexable()</code>, the same function the sitemap and the page's own robots tag use.
    If a row says NOINDEX here, that page says noindex and is absent from the sitemap. There is no
    second opinion to disagree with.
  </p>

  <section class="card" style="margin:0 0 22px"><div class="card-body">
    <h2 style="margin:0 0 8px;font-size:1.05rem">Sitemap</h2>
    <?php if (!$sitemap): ?>
      <p class="muted" style="margin:0">Nothing generated yet. It builds at deploy and on a stale read.</p>
    <?php else: ?>
      <p class="hint" style="margin:0 0 10px">
        <?= (int) $totalUrls ?> URLs across <?= count($sitemap) ?> files<?php
          if ($generated): ?>, generated <?= e(substr($generated, 0, 16)) ?><?php endif; ?>.
      </p>
      <div class="grid g-2" style="gap:2px 24px">
        <?php foreach ($sitemap as $s): ?>
          <p style="margin:2px 0;font-size:.92rem">
            <a href="<?= e(url(rmt_sitemap_filename((string) $s['group_key'], (int) $s['part']))) ?>">
              <?= e(rmt_sitemap_filename((string) $s['group_key'], (int) $s['part'])) ?></a>
            <strong style="float:right"><?= (int) $s['url_count'] ?></strong>
          </p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div></section>

  <?php foreach ($groups as $key => $g): ?>
    <?php
      $ok = array_values(array_filter($g['rows'], static fn(array $r) => $r['verdict']['ok']));
      $no = array_values(array_filter($g['rows'], static fn(array $r) => !$r['verdict']['ok']));
    ?>
    <section class="card" style="margin:0 0 22px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem"><?= e($g['label']) ?></h2>
      <p class="hint" style="margin:0 0 12px">
        <?= count($ok) ?> indexable, <?= count($no) ?> not.
        <?php if (!empty($g['rule'])): ?><?= e($g['rule']) ?><?php endif; ?>
      </p>

      <?php /* The rejected rows first and in full, because they are the ones somebody came here to
               understand. A dashboard that leads with what already works answers no questions. */ ?>
      <?php if ($no): ?>
        <table style="width:100%;border-collapse:collapse;font-size:.92rem">
          <?php foreach (array_slice($no, 0, 30) as $r): ?>
            <tr style="border-top:1px solid var(--line)">
              <td style="padding:5px 8px 5px 0"><?= e((string) $r['label']) ?></td>
              <td style="padding:5px 8px;text-align:right;white-space:nowrap" class="muted">
                <?= e((string) ($r['metric'] ?? '')) ?></td>
              <td style="padding:5px 0 5px 8px;white-space:nowrap">
                <span class="hint"><?= e(rmt_index_reason_label($r['verdict']['reason'])) ?><?php
                  if (!empty($r['verdict']['detail'])): ?> &mdash; <?= e($r['verdict']['detail']) ?><?php endif; ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
        <?php if (count($no) > 30): ?>
          <p class="hint" style="margin:8px 0 0">and <?= count($no) - 30 ?> more.</p>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($ok): ?>
        <p class="hint" style="margin:12px 0 0">
          Indexable: <?= e(implode(', ', array_map(static fn(array $r) => (string) $r['label'], array_slice($ok, 0, 25)))) ?><?php
            if (count($ok) > 25): ?> and <?= count($ok) - 25 ?> more<?php endif; ?>.
        </p>
      <?php endif; ?>
    </div></section>
  <?php endforeach; ?>
  <div style="height:40px"></div>
</div>
