<?php
/** /hosts-locals/destinations: every city, busiest first. @var array $rows */
$busy = array_values(array_filter($rows, static fn($r) => ($r['locals'] + $r['hosts'] + $r['going']) > 0));
$quiet = array_values(array_filter($rows, static fn($r) => ($r['locals'] + $r['hosts'] + $r['going']) === 0));
?>
<div class="wrap hl-sec">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a> / Cities</p>
  <h1 class="hl-h2" style="font-size:clamp(1.8rem,4vw,2.5rem)">Hosts &amp; Locals by city</h1>
  <p class="hl-sub">Locals, travelers with upcoming dates, and members interested in hosting, counted live.</p>
  <?php if ($busy): ?>
    <h2 class="hl-h2" style="font-size:1.3rem;margin-top:10px">Where members are</h2>
    <div class="hl-cities" style="margin-bottom:28px">
      <?php foreach ($busy as $c): ?>
        <a class="hl-city" href="<?= e(url('hosts-locals/destinations/' . $c['slug'])) ?>">
          <?php if (!empty($c['hero_url'])): ?><img src="<?= e((string) $c['hero_url']) ?>" alt="" loading="lazy" data-hide-broken><?php endif; ?>
          <span class="hl-city-txt"><b><?= e((string) $c['name']) ?></b>
            <small><?php $bits = [];
              if ($c['locals']) $bits[] = $c['locals'] . ' local' . ($c['locals'] === 1 ? '' : 's');
              if ($c['going'])  $bits[] = $c['going'] . ' going';
              if ($c['hosts'])  $bits[] = $c['hosts'] . ' hosting soon';
              echo e(implode(' · ', $bits)); ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <h2 class="hl-h2" style="font-size:1.3rem">Waiting for their first member</h2>
  <p class="hint">Live in one of these? You could be the first local travelers meet there.</p>
  <ul style="columns:2 180px;padding-left:18px">
    <?php foreach ($quiet as $c): ?>
      <li><a href="<?= e(url('hosts-locals/destinations/' . $c['slug'])) ?>"><?= e((string) $c['name']) ?></a> <span class="hint"><?= e((string) ($c['country'] ?? '')) ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>
