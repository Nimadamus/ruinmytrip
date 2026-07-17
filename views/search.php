<?php /** @var string $qs @var array $dests @var array $trips @var array $guides */ ?>
<div class="wrap" style="min-height:50vh">
  <h1 style="margin-top:24px">Search</h1>
  <form action="<?= e(url('search')) ?>" method="get" style="display:flex;gap:10px;margin:14px 0 26px">
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Destinations, trips, guides…" style="flex:1">
    <button class="btn btn-primary">Search</button>
  </form>
  <?php if ($qs===''): ?><p class="muted">Type a place, a trip, or a guide to begin.</p><?php else: ?>
    <?php if (!$dests && !$trips && !$guides): ?><p class="muted">No results for “<?= e($qs) ?>”.</p><?php endif; ?>
    <?php if ($dests): ?><h2>Destinations</h2><div class="grid g-3">
      <?php foreach($dests as $d):?><article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>"><img class="card-media" loading="lazy" src="<?= e($d['hero_url']) ?>" alt=""><div class="card-body"><h3 style="font-size:1.05rem"><?= e($d['name']) ?></h3></div></a></article><?php endforeach;?>
    </div><?php endif; ?>
    <?php if ($trips): ?><h2 style="margin-top:24px">Trips</h2><ul class="list-plain">
      <?php foreach($trips as $t):?><li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>"><?= e($t['title']) ?></a></li><?php endforeach;?>
    </ul><?php endif; ?>
    <?php if ($guides): ?><h2 style="margin-top:24px">Guides</h2><ul class="list-plain">
      <?php foreach($guides as $g):?><li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('g/'.$g['slug'])) ?>"><?= e($g['title']) ?></a></li><?php endforeach;?>
    </ul><?php endif; ?>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
