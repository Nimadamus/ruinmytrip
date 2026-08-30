<?php /** @var string $qs @var array $dests @var array $places @var array $trips @var array $guides @var array $reviews @var array $people @var array $posts @var array $collections @var array $talk */ ?>
<div class="wrap" style="min-height:50vh">
  <h1 style="margin-top:24px">Search</h1>
  <form action="<?= e(url('search')) ?>" method="get" style="display:flex;gap:10px;margin:14px 0 26px">
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Destinations, places, trips, reviews, guides, blog, travelers…" style="flex:1">
    <button class="btn btn-primary">Search</button>
  </form>
  <?php if ($qs===''): ?><p class="muted">Type a place, a trip, a review, or a traveler to begin.</p><?php else: ?>
    <?php if (!$dests && !$places && !$trips && !$reviews && !$guides && !$posts && !$collections && !$people && !$talk): ?><p class="muted">No results for “<?= e($qs) ?>”.</p>
      <?php /* A search that found nothing is the one moment somebody has told us exactly what we
               are missing, so this is where the missing-place flow belongs. Shown only when the
               query looks like the name of something: a queue full of typos is a queue nobody
               reads. The name is carried across so they do not type it twice. */ ?>
      <?php if (rmt_search_suggestable($qs)): ?>
        <p style="margin:10px 0 0">
          <a class="btn btn-ghost" data-review-cta="search"
             href="<?= e(url('contribute') . '?name=' . rawurlencode($qs)) ?>#suggest">Suggest &ldquo;<?= e($qs) ?>&rdquo;</a>
        </p>
        <p class="hint" style="margin:6px 0 0">
          We add places by hand after checking them, so this goes to a queue rather than straight
          onto the site.
        </p>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($dests): ?><h2>Destinations</h2><div class="grid g-3">
      <?php foreach($dests as $d):?><article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>"><img class="card-media" loading="lazy" src="<?= e($d['hero_url']) ?>" alt=""><div class="card-body"><h3 style="font-size:1.05rem"><?= e($d['name']) ?></h3></div></a></article><?php endforeach;?>
    </div><?php endif; ?>
    <?php if ($places): ?><h2 style="margin-top:24px">Places</h2><ul class="list-plain">
      <?php foreach($places as $pl):?><li style="padding:8px 0;border-bottom:1px solid var(--line)">
        <a href="<?= e(url('p/'.$pl['slug'])) ?>"><?= e($pl['name']) ?></a>
        <span class="muted"> · <span style="text-transform:capitalize"><?= e(rmt_place_type_label((string)$pl['type'])) ?></span> · <?= e($pl['dest_name']) ?>, <?= e($pl['dest_country']) ?></span>
      </li><?php endforeach;?>
    </ul><?php endif; ?>
    <?php if ($reviews): ?><h2 style="margin-top:24px">Reviews</h2><ul class="list-plain">
      <?php foreach($reviews as $r):?><li style="padding:8px 0;border-bottom:1px solid var(--line)">
        <a href="<?= e(url(ltrim(rmt_review_path($r),'/'))) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a>
        <?php if (!empty($r['dest_name'])): ?><span class="muted"> · <?= e($r['dest_name']) ?></span><?php endif; ?>
      </li><?php endforeach;?>
    </ul><?php endif; ?>
    <?php if ($trips): ?><h2 style="margin-top:24px">Trips</h2><ul class="list-plain">
      <?php foreach($trips as $t):?><li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('trip/'.$t['id'].'/'.$t['slug'])) ?>"><?= e($t['title']) ?></a></li><?php endforeach;?>
    </ul><?php endif; ?>
    <?php if ($guides): ?><h2 style="margin-top:24px">Guides</h2><ul class="list-plain">
      <?php foreach($guides as $g):?><li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('g/'.$g['slug'])) ?>"><?= e($g['title']) ?></a></li><?php endforeach;?>
    </ul><?php endif; ?>
    <?php if ($talk): ?><h2 style="margin-top:24px">Travel talk</h2><ul class="list-plain">
      <?php foreach ($talk as $tp): ?><li style="padding:8px 0;border-bottom:1px solid var(--line)">
        <a href="<?= e(url('post/'.(int) $tp['id'])) ?>"><?= e(mb_strimwidth((string) $tp['body'], 0, 110, '…')) ?></a>
        <span class="hint"> · @<?= e((string) $tp['username']) ?><?php if (!empty($tp['dest_name'])): ?> · <?= e((string) $tp['dest_name']) ?><?php endif; ?></span>
      </li><?php endforeach; ?></ul><?php endif; ?>

    <?php if ($posts): ?><h2 style="margin-top:24px">Blog</h2><ul class="list-plain">
      <?php foreach($posts as $p):?><li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('blog/'.$p['slug'])) ?>"><?= e($p['title']) ?></a></li><?php endforeach;?>
    </ul><?php endif; ?>
    <?php if ($collections): ?><h2 style="margin-top:24px">Collections</h2><ul class="list-plain">
      <?php foreach($collections as $c):?><li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('c/'.$c['slug'])) ?>"><?= e($c['title']) ?></a></li><?php endforeach;?>
    </ul><?php endif; ?>
    <?php if ($people): ?><h2 style="margin-top:24px">Travelers</h2><div class="grid" style="gap:10px">
      <?php foreach($people as $p):?>
        <article class="card"><div class="card-body" style="display:flex;gap:10px;align-items:center">
          <?php if (!empty($p['avatar_url'])): ?><img class="avatar" style="width:36px;height:36px" src="<?= e(avatar_url($p['avatar_url'])) ?>" alt=""><?php endif; ?>
          <a href="<?= e(url('u/'.$p['username'])) ?>"><?= e($p['display_name'] ?: $p['username']) ?></a>
          <span class="muted">@<?= e($p['username']) ?></span>
        </div></article>
      <?php endforeach;?>
    </div><?php endif; ?>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
