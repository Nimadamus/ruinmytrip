<?php
/**
 * "Check before you book" for one city (app/before_you_book.php). Same evidence rule as the
 * warnings above it: every item quotes the official page it comes from and links to it.
 *
 * @var array  $byb   rmt_byb()
 * @var string $bybCity display name
 */
?>
<section id="before-you-book" style="margin:26px 0 8px">
  <h2 style="margin:0 0 6px">Check before you book: <?= count($byb['items']) ?> things to know about <?= e($bybCity) ?></h2>
  <p class="muted" style="margin:0 0 14px;max-width:66ch">Rules, tickets and charges that catch visitors out, from official city, government and operator pages. Each one quotes its source.</p>
  <?php foreach ($byb['items'] as $i => $it): ?>
    <div class="card" style="margin:0 0 12px"><div class="card-body">
      <h3 class="h-card" style="margin:0 0 6px"><?= (int) ($i + 1) ?>. <?= e($it['title']) ?></h3>
      <p style="margin:0 0 8px"><?= e($it['detail']) ?></p>
      <?php if ($it['check'] !== ''): ?><p style="margin:0 0 10px"><b>Before you go:</b> <?= e($it['check']) ?></p><?php endif; ?>
      <?php foreach ($it['sources'] as $s): ?>
        <blockquote style="margin:0 0 8px;padding:6px 12px;border-left:3px solid #cbd5e1" cite="<?= e($s['url']) ?>">
          <span class="muted">&ldquo;<?= e($s['quote']) ?>&rdquo;</span>
          <span class="hint" style="display:block"><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank"><?= e($s['publisher']) ?></a></span>
        </blockquote>
      <?php endforeach; ?>
      <p class="hint" style="margin:0"><span class="chip chip-soft"><?= e(RMT_BYB_KINDS[$it['kind']]) ?></span>
        <?php if ($it['checked'] !== ''): ?>Checked <?= e(date('j M Y', strtotime($it['checked']) ?: time())) ?><?php endif; ?></p>
    </div></div>
  <?php endforeach; ?>
</section>
