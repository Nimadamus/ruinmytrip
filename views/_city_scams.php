<?php
/**
 * "Scams and traps" on a city page: official-source warnings (app/city_scams.php) and the door to
 * what travelers themselves said went wrong (/ruined?d=). Renders nothing for a city with no
 * sourced entry.
 * @var array $d the destination row
 */
$scams = rmt_city_scams((string) $d['slug']);
if (!$scams) return;
$checked = $scams['checked'] ?: ($scams['items'][0]['checked'] ?? '');
?>
<div class="card city-scams" id="scams" style="margin-bottom:18px"><div class="card-body">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <h2 class="h-card" style="margin:0">Scams and traps in <?= e((string) $d['name']) ?></h2>
    <?= rmt_editorial_badge() ?>
  </div>
  <p class="hint" style="margin:8px 0 0">What official travel advice warns visitors about, in plain words. Each point links to the
    page that says it<?= $checked ? ', checked ' . e(date('j M Y', strtotime($checked) ?: time())) : '' ?>.</p>
  <ul class="scam-list" style="margin:12px 0 0;padding-left:1.1rem">
    <?php foreach ($scams['items'] as $it): ?>
      <li style="margin:0 0 12px">
        <b><?= e($it['name']) ?>.</b> <?= e($it['what']) ?>
        <?php if ($it['avoid'] !== ''): ?><br><span class="muted"><b>Avoid it:</b> <?= e($it['avoid']) ?></span><?php endif; ?>
        <br><span class="hint">Source:
          <?php foreach ($it['sources'] as $i => $s): ?><?= $i ? ', ' : '' ?><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank"><?= e($s['publisher']) ?></a><?php endforeach; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
  <p style="margin:6px 0 0;display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('ruined?d=' . $d['slug'])) ?>">What travelers say went wrong in <?= e((string) $d['name']) ?></a>
    <a class="btn btn-accent btn-sm" data-cta="city_scams" href="<?= e(url('review/new?src=city_scams&destination=' . (int) $d['id'])) ?>">Got caught out here? Warn the next traveler</a>
  </p>
</div></div>
