<?php /** @var array $rows @var array $dests @var ?array $dest @var int $total */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if ($dest): ?><a href="<?= e(url('ruined')) ?>">What ruined it</a> / <?= e((string) $dest['name']) ?><?php else: ?>What ruined it<?php endif; ?></p></div>
<div class="wrap">
  <h1 style="margin:8px 0 6px"><?= $dest ? 'What ruined trips to ' . e((string) $dest['name']) : 'What ruined the trip' ?></h1>
  <p class="muted" style="max-width:62ch;margin:0 0 18px">One sentence each, from people who were there. The thing they wish somebody
    had warned them about. Read them before you go; add yours when you get back.</p>

  <div class="card" style="margin:0 0 26px"><div class="card-body">
    <?php $askVariant = 'card'; include __DIR__ . '/_ruined_ask.php'; ?>
  </div></div>

  <?php if ($dests && !$dest): ?>
    <p class="hint" style="margin:0 0 14px">By city:
      <?php foreach (array_slice($dests, 0, 14) as $d): ?>
        <a class="chip" href="<?= e(url('ruined?d=' . $d['slug'])) ?>"><?= e((string) $d['name']) ?></a>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>Nothing here yet<?= $dest ? ' for ' . e((string) $dest['name']) : '' ?>.</h3>
      <p class="muted" style="margin:0">Yours would be the first. That is the one people remember.</p>
    </div>
  <?php else: ?>
    <p class="hint" style="margin:0 0 10px"><?= (int) $total ?> <?= $total === 1 ? 'warning' : 'warnings' ?> so far.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;padding-bottom:50px">
      <?php foreach ($rows as $r): $href = url(ltrim(rmt_review_path($r), '/')); ?>
        <div class="card"><div class="card-body" style="display:flex;flex-direction:column;gap:8px;height:100%">
          <p style="margin:0;font-size:1.08rem;line-height:1.5;font-weight:600">“<?= e(mb_strimwidth(trim((string) $r['what_ruined']), 0, 220, '…')) ?>”</p>
          <p class="hint" style="margin:0">
            <span style="color:#d97706"><?= str_repeat('★', max(0, min(5, (int) $r['rating']))) ?></span>
            <a href="<?= e($href) ?>"><?= e((string) ($r['place_name'] ?: $r['subject_name'] ?: $r['dest_name'])) ?></a><?php if ($r['dest_name'] && ($r['place_name'] ?: $r['subject_name']) !== $r['dest_name']): ?>, <?= e((string) $r['dest_name']) ?><?php endif; ?>
          </p>
          <p class="hint" style="margin:auto 0 0">
            <?php if ($r['role'] === 'editorial' || rmt_is_editorial($r)): ?><span class="chip">Researched by us</span>
            <?php else: ?>@<a href="<?= e(url('u/' . $r['username'])) ?>"><?= e((string) $r['username']) ?></a><?php endif; ?>
            · <a href="<?= e($href) ?>">the whole review</a>
          </p>
        </div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
